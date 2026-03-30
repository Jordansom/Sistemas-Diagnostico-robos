// firebase-storage.js — Utilidad para comprimir imágenes a Base64
// La imagen se guarda directamente en Firestore como string Base64 (sin Firebase Storage).

/**
 * Comprime una imagen y la convierte a Base64 (data URL).
 * Redimensiona al ancho máximo indicado manteniendo proporción.
 * @param {File} file - Archivo de imagen
 * @param {number} maxWidth - Ancho máximo en px (default 800)
 * @param {number} quality - Calidad JPEG 0-1 (default 0.7)
 * @returns {Promise<string>} Data URL base64 de la imagen comprimida
 */
export function compressImageToBase64(file, maxWidth = 800, quality = 0.7) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error("No se pudo leer el archivo."));
    reader.onload = (e) => {
      const img = new Image();
      img.onerror = () => reject(new Error("No se pudo cargar la imagen."));
      img.onload = () => {
        let w = img.width;
        let h = img.height;
        if (w > maxWidth) {
          h = Math.round((h * maxWidth) / w);
          w = maxWidth;
        }
        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, w, h);
        const dataURL = canvas.toDataURL("image/jpeg", quality);
        resolve(dataURL);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}
