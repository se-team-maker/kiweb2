/**
 * 繝繝・す繝･繝懊・繝・JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 繝代せ繧ｭ繝ｼ逋ｻ骭ｲ繝懊ち繝ｳ
    const registerBtn = document.getElementById('register-passkey-btn');
    if (registerBtn) {
        registerBtn.addEventListener('click', registerPasskey);
    }

    // 繝代せ繧ｭ繝ｼ蜑企勁繝懊ち繝ｳ
    document.querySelectorAll('.delete-passkey').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('このパスキーを削除してよろしいですか？')) {
                deletePasskey(btn.dataset.id);
            }
        });
    });
});

/**
 * 繝代せ繧ｭ繝ｼ繧堤匳骭ｲ
 */
async function registerPasskey() {
    const btn = document.getElementById('register-passkey-btn');

    // WebAuthn蟇ｾ蠢懊メ繧ｧ繝・け
    if (!window.PublicKeyCredential) {
        alert('このブラウザはパスキーに対応していません');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="loading"></span> 登録中...';

    try {
        // 逋ｻ骭ｲ繧ｪ繝励す繝ｧ繝ｳ繧貞叙蠕・
        const optionsResponse = await fetch('/kiweb/teacher-auth/api/webauthn/register-options.php', {
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

        // Base64繧但rrayBuffer縺ｫ螟画鋤
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

        // WebAuthn逋ｻ骭ｲ
        const credential = await navigator.credentials.create({
            publicKey: options
        });

        // 逋ｻ骭ｲ邨先棡繧偵し繝ｼ繝舌・縺ｫ騾∽ｿ｡
        const verifyResponse = await fetch('/kiweb/teacher-auth/api/webauthn/register-verify.php', {
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
 * 繝代せ繧ｭ繝ｼ繧貞炎髯､
 */
async function deletePasskey(credentialId) {
    try {
        const response = await fetch('/kiweb/teacher-auth/api/webauthn/delete.php', {
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
 * Base64繧但rrayBuffer縺ｫ螟画鋤
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
 * ArrayBuffer繧達ase64縺ｫ螟画鋤
 */
function bufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
