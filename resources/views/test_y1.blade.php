<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dynamic PDF Filler</title>
  <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
  <style>
    body { font-family: sans-serif; padding: 20px; }
    #inputs { margin-top: 20px; }
    input { display: block; margin-bottom: 10px; width: 300px; padding: 6px; }
  </style>
</head>
<body>
  <h2>Upload PDF and Fill Dynamically</h2>

  <input type="file" id="pdfFile" accept="application/pdf">
  <button id="load">Load Fields</button>

  <form id="inputs"></form>

  <button id="fill" style="display:none;">Fill & Download PDF</button>

  <script>
    let fieldNames = [];
    let pdfDoc;

    document.getElementById('load').addEventListener('click', async () => {
      const file = document.getElementById('pdfFile').files[0];
      if (!file) return alert('Choose a PDF first.');

      const arrayBuffer = await file.arrayBuffer();
      pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
      const form = pdfDoc.getForm();
      const fields = form.getFields();

      fieldNames = fields.map(f => f.getName());

      // إنشاء مدخلات تلقائيًا
      const formContainer = document.getElementById('inputs');
      formContainer.innerHTML = '';
      fieldNames.forEach(name => {
        const input = document.createElement('input');
        input.placeholder = name;
        input.name = name;
        formContainer.appendChild(input);
      });

      document.getElementById('fill').style.display = 'inline-block';
      alert('Fields loaded! Fill the inputs below.');
    });

    document.getElementById('fill').addEventListener('click', async (e) => {
      e.preventDefault();
      if (!pdfDoc) return alert('Load a PDF first.');

      const form = pdfDoc.getForm();

      fieldNames.forEach(name => {
        const value = document.querySelector(`[name="${CSS.escape(name)}"]`).value || '';
        try {
          form.getTextField(name).setText(value);
        } catch (e) {
          console.warn('Could not set field:', name, e);
        }
      });

      const pdfBytes = await pdfDoc.save();
      const blob = new Blob([pdfBytes], { type: 'application/pdf' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'filled.pdf';
      link.click();
    });
  </script>
</body>
</html>
