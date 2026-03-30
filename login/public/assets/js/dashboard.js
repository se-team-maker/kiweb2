/**
 * ダッシュボード JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // パスキー登録ボタン
    const registerBtn = document.getElementById('register-passkey-btn');
    if (registerBtn) {
        registerBtn.addEventListener('click', registerPasskey);
    }

    // パスキー削除ボタン
    document.querySelectorAll('.delete-passkey').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('このパスキーを削除しますか？')) {
                deletePasskey(btn.dataset.id);
            }
        });
    });
});

/**
 * パスキーを登録
 */
async function registerPasskey() {
    const btn = document.getElementById('register-passkey-btn');

    // WebAuthn対応チェック
    if (!window.PublicKeyCredential) {
        alert('このブラウザはパスキーに対応していません');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="loading"></span> 登録中...';

    try {
        // 登録オプションを取得
        const optionsResponse = await fetch('/kiweb/login/api/webauthn/register-options.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken
            }
        });

        const optionsResult = await optionsResponse.json();

        if (!optionsResult.success) {
            throw new Error(optionsResult.error || '登録オプションの取得に失敗しました');
        }

        const options = optionsResult.options;

        // Base64をArrayBufferに変換
        options.challenge = base64ToBuffer(options.challenge);
        options.user.id = base64ToBuffer(options.user.id);
        if (options.excludeCredentials) {
            options.excludeCredentials = options.excludeCredentials.map(cred => ({
                ...cred,
                id: base64ToBuffer(cred.id)
            }));
        }

        // デバイス名を入力
        const deviceName = prompt('このパスキーの名前を入力してください（例：iPhone、MacBook）', '');

        // WebAuthn登録
        const credential = await navigator.credentials.create({
            publicKey: options
        });

        // 登録結果をサーバーに送信
        const verifyResponse = await fetch('/kiweb/login/api/webauthn/register-verify.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken
            },
            body: JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64(credential.rawId),
                response: {
                    attestationObject: bufferToBase64(credential.response.attestationObject),
                    clientDataJSON: bufferToBase64(credential.response.clientDataJSON)
                },
                type: credential.type,
                deviceName: deviceName || null
            })
        });

        const verifyResult = await verifyResponse.json();

        if (verifyResult.success) {
            alert('パスキーを登録しました');
            location.reload();
        } else {
            throw new Error(verifyResult.error || '登録に失敗しました');
        }
    } catch (error) {
        if (error.name === 'NotAllowedError') {
            alert('パスキーの登録がキャンセルされました');
        } else {
            alert(error.message || 'パスキーの登録に失敗しました');
        }
    } finally {
        btn.disabled = false;
        btn.textContent = 'パスキーを登録';
    }
}

/**
 * パスキーを削除
 */
async function deletePasskey(credentialId) {
    try {
        const response = await fetch('/kiweb/login/api/webauthn/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken
            },
            body: JSON.stringify({ credential_id: credentialId })
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert(result.error || '削除に失敗しました');
        }
    } catch (error) {
        alert('通信エラーが発生しました');
    }
}

/**
 * Base64をArrayBufferに変換
 */
function base64ToBuffer(base64) {
    const binary = atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

/**
 * ArrayBufferをBase64に変換
 */
function bufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
