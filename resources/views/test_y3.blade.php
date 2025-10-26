<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View PDF Fields</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"></script>
  <style>
    #pdf-viewer {
      width: 100%;
      height: 90vh;
      border: 1px solid #ccc;
    }
  </style>
</head>
<body>

<canvas id="pdf-viewer"></canvas>

<script>
  // Laravel asset path
  const pdfUrl = "{{ asset('files/md.pdf') }}";

  const loadingTask = pdfjsLib.getDocument(pdfUrl);
  loadingTask.promise.then(async function(pdf) {
    console.log('✅ PDF loaded with ' + pdf.numPages + ' pages.');

    const page = await pdf.getPage(1);
    const scale = 1.5;
    const viewport = page.getViewport({ scale });

    const canvas = document.getElementById('pdf-viewer');
    const context = canvas.getContext('2d');
    canvas.height = viewport.height;
    canvas.width = viewport.width;

    // Render the first page
    const renderContext = {
      canvasContext: context,
      viewport: viewport
    };
    await page.render(renderContext).promise;
    console.log('✅ Page rendered.');

    // Extract and log all form fields
    const annotations = await page.getAnnotations();
    console.log("📋 PDF Fields Found:");
    annotations.forEach((ann, i) => {
      if (ann.fieldName) {
        console.log(
          `#${i + 1} → name: ${ann.fieldName}, type: ${ann.fieldType}, rect:`, ann.rect
        );
      }
    });
  }).catch(err => {
    console.error('❌ Error loading PDF:', err);
  });
</script>

</body>
</html>
