<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Get PDF Field Positions</title>
  <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
</head>
<body>
  <h3>Upload PDF to see field positions</h3>
  <input type="file" id="pdfFile" accept="application/pdf">
  <button id="showFields">Show Field Positions</button>

  <script>
    document.getElementById('showFields').addEventListener('click', async () => {
      const file = document.getElementById('pdfFile').files[0];
      if (!file) return alert('Choose a PDF first.');

      const arrayBuffer = await file.arrayBuffer();
      const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);

      const form = pdfDoc.getForm();
      const fields = form.getFields();

      console.log('📍 PDF Fields Found:');
      for (const field of fields) {
        const name = field.getName();
        const type = field.constructor.name;
        const widgets = field.acroField.getWidgets();

        widgets.forEach((widget, i) => {
          const rect = widget.getRectangle ? widget.getRectangle() : widget.dict.get('Rect');
          let coords;

          if (rect && rect.length === 4) {
            // بعض الإصدارات بترجع مصفوفة [x1, y1, x2, y2]
            const [x1, y1, x2, y2] = rect;
            coords = { x: x1, y: y1, width: x2 - x1, height: y2 - y1 };
          } else if (rect && rect.x !== undefined) {
            // لو الكائن فيه خصائص جاهزة
            coords = { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
          } else {
            coords = { x: '?', y: '?', width: '?', height: '?' };
          }

          console.log({
            name,
            type,
            widgetIndex: i + 1,
            x: coords.x,
            y: coords.y,
            width: coords.width,
            height: coords.height
          });
        });
      }
    });
  </script>
</body>
</html>
