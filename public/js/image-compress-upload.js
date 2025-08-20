(() => {
  const MAX_BYTES = 5 * 1024 * 1024;           // 5MB
  const QUALITY_STEPS = [0.85, 0.75, 0.65, 0.55, 0.45];
  const SHRINK_FACTOR = 0.85;
  const MIN_SIDE = 256;
  const MAX_ITER = 12;

  const form = document.getElementById('bbs-form');
  const fileInput = document.getElementById('image-input');
  if (!form) return;

  // WebPサポート判定
  let canWebP = false;
  (function detectWebP() {
    try {
      const c = document.createElement('canvas');
      canWebP = c.toDataURL && c.toDataURL('image/webp').startsWith('data:image/webp');
    } catch { canWebP = false; }
  })();

  // <canvas>に描画（可能ならEXIF回転を考慮）
  async function drawToCanvas(file, targetW, targetH) {
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const c = document.createElement('canvas');
      c.width = targetW || bmp.width;
      c.height = targetH || bmp.height;
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      bmp.close?.();
      return c;
    } catch {
      // フォールバック: <img>で読み込み
      const img = await new Promise((res, rej) => {
        const i = new Image();
        i.onload = () => res(i);
        i.onerror = rej;
        i.src = URL.createObjectURL(file);
      });
      const c = document.createElement('canvas');
      c.width = targetW || img.naturalWidth;
      c.height = targetH || img.naturalHeight;
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(img.src);
      return c;
    }
  }

  // キャンバス→Blob
  function canvasToBlob(canvas, type, quality) {
    return new Promise((resolve, reject) => {
      if (canvas.toBlob) {
        canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob failed')), type, quality);
      } else {
        const dataUrl = canvas.toDataURL(type, quality);
        const bin = atob((dataUrl.split(',')[1] || ''));
        const arr = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        resolve(new Blob([arr], { type }));
      }
    });
  }

  // 5MB以下に圧縮（WebP優先／非対応はJPEG）
  async function compressToUnder5MB(file) {
    if (!file || !file.type.startsWith('image/')) {
      return { blob: null, filename: null };
    }

    let canvas = await drawToCanvas(file);
    let w = canvas.width, h = canvas.height;

    const targetType = canWebP ? 'image/webp' : 'image/jpeg';
    const targetExt  = canWebP ? 'webp' : 'jpg';

    let attempt = 0;
    let qIndex = 0;
    let quality = QUALITY_STEPS[qIndex];
    let blob = await canvasToBlob(canvas, targetType, quality);

    while (attempt < MAX_ITER) {
      attempt++;

      if (blob.size <= MAX_BYTES) {
        const base = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        return {
          blob,
          filename: `${base}.${targetExt}`
        };
      }

      // まだ大きい → 品質を下げる
      if (qIndex < QUALITY_STEPS.length - 1) {
        qIndex++;
        quality = QUALITY_STEPS[qIndex];
        blob = await canvasToBlob(canvas, targetType, quality);
        continue;
      }

      // それでもダメ → 解像度を落とす
      const newW = Math.floor(w * SHRINK_FACTOR);
      const newH = Math.floor(h * SHRINK_FACTOR);
      if (newW < MIN_SIDE || newH < MIN_SIDE) break;

      const nextCanvas = document.createElement('canvas');
      nextCanvas.width = newW; nextCanvas.height = newH;
      nextCanvas.getContext('2d').drawImage(canvas, 0, 0, newW, newH);
      canvas = nextCanvas;
      w = newW; h = newH;

      // 解像度を落としたら品質は少し戻す（見た目優先）
      qIndex = Math.min(2, QUALITY_STEPS.length - 1); // 0.65
      quality = QUALITY_STEPS[qIndex];
      blob = await canvasToBlob(canvas, targetType, quality);
    }

    // 稀にサイズ未達のまま終わる場合は現状のblobを返す（サーバ側で最終チェック推奨）
    const base = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
    return {
      blob,
      filename: `${base}.${targetExt}`
    };
  }

  // submit横取り→圧縮→fetchでPOST→画面戻し
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const file = fileInput?.files?.[0] || null;
    const formData = new FormData();

    // 本文
    const bodyField = form.querySelector('textarea[name="body"]');
    if (bodyField) formData.append('body', bodyField.value ?? '');

    // hidden（CSRFなど）
    for (const el of form.querySelectorAll('input[type="hidden"]')) {
      if (el.name) formData.append(el.name, el.value ?? '');
    }

    // 画像があれば圧縮して追加
    if (file) {
      try {
        const { blob, filename } = await compressToUnder5MB(file);
        if (blob) {
          const wrapped = new File([blob], filename, { type: blob.type, lastModified: Date.now() });
          formData.append('image', wrapped, wrapped.name);
        }
      } catch (err) {
        console.error(err);
        alert('画像の圧縮に失敗しました。元の画像をそのまま送信します。');
        formData.append('image', file, file.name);
      }
    }

    // 送信
    const action = form.getAttribute('action') || location.href;
    const res = await fetch(action, { method: 'POST', body: formData, credentials: 'same-origin' });

    if (res.ok) {
      // 二重送信防止でGETに戻す
      window.location.href = './bbs.php';
    } else {
      alert('送信に失敗しました。ステータス: ' + res.status);
    }
  });
})();

