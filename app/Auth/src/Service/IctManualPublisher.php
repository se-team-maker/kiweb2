<?php
/**
 * ICTマニュアルの入力検証とファイル公開を担当するサービス。
 *
 * HTTPやログインには依存せず、APIから渡された一時ファイルとメタ情報を検証して、
 * 既存のICTマニュアル構成へ反映する。
 *
 * 保存先:
 * - manuals/{id}.md
 * - assets/images/{id}/{画像ファイル名}
 * - manuals/index.json
 *
 * 同じidがindex.jsonにあれば置換し、なければ追加する。複数リクエストが同時に
 * index.jsonを更新して内容が消えないよう、公開処理中はファイルロックを取得する。
 */

namespace App\Service;

use DateTime;
use RuntimeException;

class IctManualPublisher
{
    // PHP設定とは別に、アプリケーション側でも上限を固定して過大な入力を拒否する。
    private const MAX_MARKDOWN_BYTES = 1048576;
    private const MAX_IMAGE_BYTES = 5242880;
    private const MAX_IMAGE_COUNT = 20;
    private const MAX_TOTAL_IMAGE_BYTES = 31457280;
    private const MAX_IMAGE_DIMENSION = 12000;

    // index.jsonへ書き込める値を列挙し、未知の値が閲覧権限制御へ流れないようにする。
    private const ALLOWED_TARGETS = array('all', 'parttime', 'fulltime', 'admin');
    private const ALLOWED_PRIORITIES = array('high', 'medium', 'low');
    private const ALLOWED_METADATA_KEYS = array(
        'id',
        'title',
        'category',
        'file',
        'description',
        'updated',
        'target',
        'tags',
        'priority',
        'order',
        'visible'
    );
    // SVGはスクリプトを含められるため対象外。画像の実体MIMEと保存拡張子を対応付ける。
    private const ALLOWED_IMAGE_MIME_TYPES = array(
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    );

    /**
     * すべての入力を検証し、ICTマニュアルへ即時公開する。
     *
     * 検証が完了するまでは本番ファイルへ書き込まない。検証後にロックを取得し、
     * Markdown、画像、index.jsonの順で反映する。index.jsonを最後に更新することで、
     * 一覧から新しい本文を参照した時点では本文と画像が配置済みの状態にする。
     *
     * @return array 公開したid、保存名、画像数、更新日
     */
    public static function publish($baseDir, array $metadata, $markdownPath, array $images)
    {
        $baseDir = rtrim((string)$baseDir, DIRECTORY_SEPARATOR);
        $manualsDir = $baseDir . DIRECTORY_SEPARATOR . 'manuals';
        $imagesDir = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';

        self::requireWritableDirectory($manualsDir, 'マニュアル保存フォルダ');
        self::requireWritableDirectory($imagesDir, '画像保存フォルダ');

        // 先に全入力を検証し、途中まで保存された状態をできるだけ作らない。
        $normalized = self::normalizeMetadata($metadata);
        $markdown = self::validateMarkdown($markdownPath, $normalized['id']);
        $validatedImages = self::validateImages($images);
        self::validateImageReferences($markdown, $normalized['id'], $validatedImages, $imagesDir);

        // index.jsonは共有資源なので、読み込みから書き戻しまでを1つの排他区間にする。
        $lockPath = $manualsDir . DIRECTORY_SEPARATOR . '.upload.lock';
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            throw new RuntimeException('更新ロックを作成できません。');
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('更新ロックを取得できません。');
            }

            // ロック取得後に最新index.jsonを読み直す。ロック待ち中の更新を上書きしないため。
            $indexPath = $manualsDir . DIRECTORY_SEPARATOR . 'index.json';
            $items = self::readIndex($indexPath);
            $items = self::upsertIndexItem($items, $normalized);

            // 保存名はアップロード元のファイル名を使わず、検証済みidから固定する。
            $manualPath = $manualsDir . DIRECTORY_SEPARATOR . $normalized['file'];
            self::atomicWrite($manualPath, $markdown);

            if ($validatedImages !== array()) {
                $manualImageDir = $imagesDir . DIRECTORY_SEPARATOR . $normalized['id'];
                self::ensureDirectory($manualImageDir);

                foreach ($validatedImages as $image) {
                    self::atomicCopy(
                        $image['tmp_path'],
                        $manualImageDir . DIRECTORY_SEPARATOR . $image['file_name']
                    );
                }
            }

            // index.jsonは人手でも確認・編集できる既存運用を保つため整形して保存する。
            $indexJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($indexJson === false) {
                throw new RuntimeException('index.jsonを生成できません。');
            }
            self::atomicWrite($indexPath, $indexJson . "\n");

            flock($lockHandle, LOCK_UN);
        } finally {
            fclose($lockHandle);
        }

        return array(
            'id' => $normalized['id'],
            'file' => $normalized['file'],
            'image_count' => count($validatedImages),
            'updated' => $normalized['updated']
        );
    }

    /**
     * 外部JSONをindex.jsonへ保存可能な既知の構造へ正規化する。
     *
     * 未知の項目は無視せずエラーにし、入力側のスペルミスや仕様違いを即時に発見する。
     */
    public static function normalizeMetadata(array $metadata)
    {
        foreach (array_keys($metadata) as $key) {
            if (!in_array((string)$key, self::ALLOWED_METADATA_KEYS, true)) {
                throw new RuntimeException('未対応のJSON項目があります: ' . (string)$key);
            }
        }

        $id = self::requiredString($metadata, 'id', 80);
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/', $id) !== 1) {
            throw new RuntimeException('idは英数字・ハイフン・アンダースコアで指定してください。');
        }

        // fileを自由入力にすると別ディレクトリや別マニュアルを上書きできるためidから固定する。
        $generatedFile = $id . '.md';
        if (array_key_exists('file', $metadata)) {
            if (!is_string($metadata['file']) || trim($metadata['file']) !== $generatedFile) {
                throw new RuntimeException('fileを指定する場合は "' . $generatedFile . '" にしてください。');
            }
        }

        $updated = self::requiredString($metadata, 'updated', 10);
        $date = DateTime::createFromFormat('!Y-m-d', $updated);
        if ($date === false || $date->format('Y-m-d') !== $updated) {
            throw new RuntimeException('updatedは実在する日付をYYYY-MM-DD形式で指定してください。');
        }

        if (!isset($metadata['target']) || !is_array($metadata['target'])) {
            throw new RuntimeException('targetは配列で指定してください。');
        }

        $targets = array();
        foreach ($metadata['target'] as $target) {
            if (!is_string($target)) {
                throw new RuntimeException('targetの各要素は文字列で指定してください。');
            }
            $target = strtolower(trim($target));
            if (!in_array($target, self::ALLOWED_TARGETS, true)) {
                throw new RuntimeException('targetには all / parttime / fulltime / admin のみ指定できます。');
            }
            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }
        if ($targets === array()) {
            throw new RuntimeException('targetは1つ以上指定してください。');
        }
        // allと個別対象の併記は意味が重複し、設定ミスも見つけにくいため禁止する。
        if (in_array('all', $targets, true) && count($targets) !== 1) {
            throw new RuntimeException('targetにallを指定する場合、他の対象は同時に指定できません。');
        }

        $tags = array();
        if (array_key_exists('tags', $metadata)) {
            if (!is_array($metadata['tags'])) {
                throw new RuntimeException('tagsは配列で指定してください。');
            }
            if (count($metadata['tags']) > 20) {
                throw new RuntimeException('tagsは20件以下にしてください。');
            }
            foreach ($metadata['tags'] as $tag) {
                if (!is_string($tag)) {
                    throw new RuntimeException('tagsの各要素は文字列で指定してください。');
                }
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }
                self::assertUtf8($tag, 'tag');
                if (self::stringLength($tag) > 40) {
                    throw new RuntimeException('各tagは40文字以下にしてください。');
                }
                if (!in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }

        $priority = array_key_exists('priority', $metadata)
            ? strtolower(self::stringValue($metadata['priority'], 'priority'))
            : 'medium';
        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            throw new RuntimeException('priorityには high / medium / low のみ指定できます。');
        }

        $order = array_key_exists('order', $metadata) ? $metadata['order'] : 999;
        if (!is_int($order) || $order < 0 || $order > 9999) {
            throw new RuntimeException('orderは0から9999の整数で指定してください。');
        }

        $visible = array_key_exists('visible', $metadata) ? $metadata['visible'] : true;
        if (!is_bool($visible)) {
            throw new RuntimeException('visibleはtrueまたはfalseで指定してください。');
        }

        return array(
            'id' => $id,
            'title' => self::requiredString($metadata, 'title', 120),
            'category' => self::requiredString($metadata, 'category', 80),
            'file' => $generatedFile,
            'description' => self::requiredString($metadata, 'description', 300),
            'updated' => $updated,
            'target' => $targets,
            'tags' => $tags,
            'priority' => $priority,
            'order' => $order,
            'visible' => $visible
        );
    }

    /**
     * Markdown本文を読み込み、文字コード・サイズ・画像パスを検証する。
     *
     * 画像参照はimages/{id}/以下だけを許可し、別マニュアルの画像や相対パスによる
     * ディレクトリ移動を防ぐ。
     */
    public static function validateMarkdown($path, $id)
    {
        $path = (string)$path;
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Markdownファイルを読み込めません。');
        }

        $size = filesize($path);
        if ($size === false || $size < 1) {
            throw new RuntimeException('Markdownファイルが空です。');
        }
        if ($size > self::MAX_MARKDOWN_BYTES) {
            throw new RuntimeException('Markdownファイルは1MB以下にしてください。');
        }

        $markdown = file_get_contents($path);
        if ($markdown === false || trim($markdown) === '') {
            throw new RuntimeException('Markdownファイルが空です。');
        }
        if (strpos($markdown, "\0") !== false) {
            throw new RuntimeException('Markdownファイルに不正な文字が含まれています。');
        }
        self::assertUtf8($markdown, 'Markdown');

        // 現在の閲覧画面が理解できるMarkdown画像構文だけを検査対象にする。
        $expectedPrefix = 'images/' . $id . '/';
        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $markdown, $matches);
        foreach ($matches[1] as $reference) {
            $reference = trim((string)$reference);
            if (strpos($reference, $expectedPrefix) !== 0) {
                throw new RuntimeException('画像パスは "' . $expectedPrefix . 'ファイル名" 形式にしてください。');
            }
            $fileName = substr($reference, strlen($expectedPrefix));
            if (!self::isSafeImageFileName($fileName)) {
                throw new RuntimeException('Markdown内の画像ファイル名が不正です: ' . $fileName);
            }
        }

        return $markdown;
    }

    /**
     * 画像ファイル名、容量、画像実体、MIMEタイプ、拡張子を検証する。
     *
     * $_FILESのtypeは送信者が変更できるため使用せず、finfoとgetimagesizeで
     * サーバー上の一時ファイルを直接確認する。
     */
    public static function validateImages(array $images)
    {
        if (count($images) > self::MAX_IMAGE_COUNT) {
            throw new RuntimeException('画像は1回につき20枚以下にしてください。');
        }

        $validated = array();
        $totalBytes = 0;
        $seenNames = array();

        foreach ($images as $image) {
            if (!is_array($image)) {
                throw new RuntimeException('画像アップロード情報が不正です。');
            }

            $tmpPath = isset($image['tmp_path']) ? (string)$image['tmp_path'] : '';
            $originalName = isset($image['name']) ? basename((string)$image['name']) : '';
            if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
                throw new RuntimeException('画像ファイルを読み込めません。');
            }
            if (!self::isSafeImageFileName($originalName)) {
                throw new RuntimeException('画像ファイル名は英数字・ハイフン・アンダースコアのみ使用できます: ' . $originalName);
            }
            if (isset($seenNames[$originalName])) {
                throw new RuntimeException('同じ画像ファイル名が複数指定されています: ' . $originalName);
            }
            $seenNames[$originalName] = true;

            $size = filesize($tmpPath);
            if ($size === false || $size < 1) {
                throw new RuntimeException('空の画像は登録できません: ' . $originalName);
            }
            if ($size > self::MAX_IMAGE_BYTES) {
                throw new RuntimeException('画像は1枚5MB以下にしてください: ' . $originalName);
            }
            $totalBytes += $size;
            if ($totalBytes > self::MAX_TOTAL_IMAGE_BYTES) {
                throw new RuntimeException('画像の合計サイズは30MB以下にしてください。');
            }

            // デコード可能な画像か、異常に大きな寸法を持たないかを確認する。
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                throw new RuntimeException('画像として確認できません: ' . $originalName);
            }
            if ((int)$imageInfo[0] > self::MAX_IMAGE_DIMENSION || (int)$imageInfo[1] > self::MAX_IMAGE_DIMENSION) {
                throw new RuntimeException('画像の縦横サイズは12000px以下にしてください: ' . $originalName);
            }

            // 拡張子だけ偽装したファイルを受け入れないよう、内容からMIMEを判定する。
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmpPath);
            if (!isset(self::ALLOWED_IMAGE_MIME_TYPES[$mime])) {
                throw new RuntimeException('PNG・JPEG・GIF・WebP画像のみ登録できます: ' . $originalName);
            }

            $expectedExtension = self::ALLOWED_IMAGE_MIME_TYPES[$mime];
            $actualExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $extensionMatches = $actualExtension === $expectedExtension
                || ($expectedExtension === 'jpg' && $actualExtension === 'jpeg');
            if (!$extensionMatches) {
                throw new RuntimeException('画像の内容と拡張子が一致しません: ' . $originalName);
            }

            $validated[] = array(
                'tmp_path' => $tmpPath,
                'file_name' => $originalName,
                'mime' => $mime,
                'size' => $size
            );
        }

        return $validated;
    }

    /**
     * Markdownが参照する画像が、今回のアップロードまたは既存保存先に存在するか確認する。
     *
     * 更新時に画像を毎回送り直さなくても、既存画像を引き続き参照できる仕様。
     */
    private static function validateImageReferences($markdown, $id, array $images, $imagesDir)
    {
        $available = array();
        foreach ($images as $image) {
            $available[$image['file_name']] = true;
        }

        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', (string)$markdown, $matches);
        $prefix = 'images/' . $id . '/';
        foreach ($matches[1] as $reference) {
            $fileName = substr(trim((string)$reference), strlen($prefix));
            if (isset($available[$fileName])) {
                continue;
            }

            $existingPath = rtrim((string)$imagesDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . $id
                . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($existingPath)) {
                throw new RuntimeException('Markdownが参照する画像がありません: ' . $reference);
            }
        }
    }

    /**
     * 現在のindex.jsonを配列として読み込む。
     *
     * 壊れたindexへ追記して被害を広げないよう、形式不正時は公開処理を中止する。
     */
    private static function readIndex($path)
    {
        if (!is_readable($path)) {
            throw new RuntimeException('index.jsonを読み込めません。');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('index.jsonを読み込めません。');
        }

        $items = json_decode($raw, true);
        if (!is_array($items) || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new RuntimeException('index.jsonの形式が不正です。');
        }

        return $items;
    }

    /**
     * id一致なら置換し、一致しなければ追加する。
     *
     * 同じidが既存indexに複数ある場合は、どれを正とするか決められないため拒否する。
     */
    private static function upsertIndexItem(array $items, array $metadata)
    {
        $matched = false;
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException('index.jsonに不正な項目があります。');
            }
            if (isset($item['id']) && (string)$item['id'] === $metadata['id']) {
                if ($matched) {
                    throw new RuntimeException('index.jsonに同じidが複数存在します。');
                }
                $items[$index] = $metadata;
                $matched = true;
            }
        }

        if (!$matched) {
            $items[] = $metadata;
        }

        // 既存閲覧APIと同じくorder優先、同値ならtitle順で安定した一覧にする。
        usort($items, function ($a, $b) {
            $ao = isset($a['order']) ? (int)$a['order'] : 9999;
            $bo = isset($b['order']) ? (int)$b['order'] : 9999;
            if ($ao === $bo) {
                return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
            }
            return $ao < $bo ? -1 : 1;
        });

        return array_values($items);
    }

    private static function requiredString(array $metadata, $key, $maxLength)
    {
        if (!array_key_exists($key, $metadata)) {
            throw new RuntimeException($key . 'は必須です。');
        }

        $value = self::stringValue($metadata[$key], $key);
        if ($value === '') {
            throw new RuntimeException($key . 'は必須です。');
        }
        self::assertUtf8($value, $key);
        if (self::stringLength($value) > (int)$maxLength) {
            throw new RuntimeException($key . 'は' . (int)$maxLength . '文字以下にしてください。');
        }

        return $value;
    }

    private static function stringValue($value, $key)
    {
        if (!is_string($value)) {
            throw new RuntimeException($key . 'は文字列で指定してください。');
        }
        return trim($value);
    }

    private static function assertUtf8($value, $label)
    {
        if (preg_match('//u', (string)$value) !== 1) {
            throw new RuntimeException($label . 'はUTF-8で指定してください。');
        }
    }

    private static function stringLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string)$value, 'UTF-8');
        }
        return strlen((string)$value);
    }

    private static function isSafeImageFileName($fileName)
    {
        $fileName = (string)$fileName;
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\.(png|jpe?g|gif|webp)\z/i', $fileName) === 1
            && strpos($fileName, '..') === false;
    }

    private static function requireWritableDirectory($dir, $label)
    {
        if (!is_dir($dir)) {
            throw new RuntimeException($label . 'がありません。');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException($label . 'に書き込みできません。');
        }
    }

    private static function ensureDirectory($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('画像保存フォルダを作成できません。');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('画像保存フォルダに書き込みできません。');
        }
    }

    /**
     * 同じディレクトリへ一時ファイルを書き、renameで完成版へ置き換える。
     *
     * 直接上書き中のファイルを閲覧処理が読んで、途中までの内容を返すことを避ける。
     */
    private static function atomicWrite($path, $content)
    {
        $tmpPath = dirname($path) . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($tmpPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('ファイルを書き込めません: ' . basename($path));
        }
        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new RuntimeException('ファイルを反映できません: ' . basename($path));
        }
    }

    /**
     * アップロード一時画像も、完成後にrenameして公開先へ切り替える。
     */
    private static function atomicCopy($source, $destination)
    {
        $tmpPath = dirname($destination) . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(8));
        if (!copy($source, $tmpPath)) {
            throw new RuntimeException('画像を書き込めません: ' . basename($destination));
        }
        if (!rename($tmpPath, $destination)) {
            @unlink($tmpPath);
            throw new RuntimeException('画像を反映できません: ' . basename($destination));
        }
    }
}
