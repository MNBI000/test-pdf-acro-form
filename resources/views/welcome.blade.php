<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit PDF (View Only)</title>
  <style>
    #pdf-container {
      position: relative;
      width: 100%;
      height: 90vh;
      border: 1px solid #ccc;
      overflow: auto;
    }

    #annotation-layer {
      position: absolute;
      top: 0;
      left: 0;
      pointer-events: none; /* يخليها ما تمنع التحكم بالماوس داخل الـ PDF */
    }

    #tools {
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<div id="tools">
  <button id="drawText">✏️ Add Text</button>
  <button id="drawRect">🟥 Draw Rectangle</button>
  <button id="clear">🧹 Clear All</button>
</div>

<div id="pdf-container">
  <canvas id="pdf-canvas"></canvas>
  <canvas id="annotation-layer"></canvas>
</div>

<script type="module">
  import * as pdfjsLib from "{{ asset('pdfjs/pdf.mjs') }}";

  pdfjsLib.GlobalWorkerOptions.workerSrc = "{{ asset('pdfjs/pdf.worker.mjs') }}";

  const url = "{{ asset('files/md.pdf') }}";

  // تحميل PDF
  const loadingTask = pdfjsLib.getDocument(url);
  loadingTask.promise.then(pdf => {
    return pdf.getPage(1);
  }).then(page => {
    const scale = 1.3;
    const viewport = page.getViewport({ scale });

    // إعداد Canvas الرئيسي
    const pdfCanvas = document.getElementById('pdf-canvas');
    const pdfCtx = pdfCanvas.getContext('2d');
    pdfCanvas.height = viewport.height;
    pdfCanvas.width = viewport.width;

    // Canvas للتعليقات أو الرسم
    const overlay = document.getElementById('annotation-layer');
    const ctx = overlay.getContext('2d');
    overlay.height = viewport.height;
    overlay.width = viewport.width;

    // عرض الصفحة
    const renderContext = {
      canvasContext: pdfCtx,
      viewport: viewport
    };
    page.render(renderContext);

    // أدوات الرسم
    const drawTextBtn = document.getElementById('drawText');
    const drawRectBtn = document.getElementById('drawRect');
    const clearBtn = document.getElementById('clear');

    drawTextBtn.addEventListener('click', () => {
      overlay.style.pointerEvents = 'auto';
      overlay.onclick = function(e) {
        const text = prompt("اكتب النص اللي عايز تضيفه:");
        if (text) {
          ctx.font = "20px Arial";
          ctx.fillStyle = "red";
          ctx.fillText(text, e.offsetX, e.offsetY);
        }
        overlay.style.pointerEvents = 'none';
      };
    });

    drawRectBtn.addEventListener('click', () => {
      overlay.style.pointerEvents = 'auto';
      overlay.onclick = function(e) {
        ctx.strokeStyle = "blue";
        ctx.lineWidth = 3;
        ctx.strokeRect(e.offsetX - 50, e.offsetY - 25, 100, 50);
        overlay.style.pointerEvents = 'none';
      };
    });

    clearBtn.addEventListener('click', () => {
      ctx.clearRect(0, 0, overlay.width, overlay.height);
    });
  });
</script>
</body>
</html>
