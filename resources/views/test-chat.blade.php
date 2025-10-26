<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <title>pdf-lib + PDF.js overlay demo</title>
    <style>
        #pdfContainer {
            max-width: 900px;
            margin: 20px auto;
        }

        .pageWrapper {
            position: relative;
            margin-bottom: 24px;
            background: #eee;
        }

        canvas {
            display: block;
            width: 100%;
            height: auto;
        }

        .overlays {
            position: absolute;
            left: 0;
            top: 0;
            pointer-events: none;
        }

        .field-box {
            position: absolute;
            border: 2px solid #000;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.05);
            pointer-events: auto;
            /* set to auto if inputs are interactive */
            font-size: 12px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .field-input {
            position: absolute;
            border: none;
            outline: none;
            background: transparent;
            font-size: 12px;
            padding: 0 4px;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div style="text-align:center;">
        <button id="showFields">Render PDF + Show Fields</button>
    </div>
    <div id="pdfContainer"></div>

    <!-- pdf-lib (you already use this) -->
    <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>

    <script>
        // Helper: normalize a Rect value coming from pdf-lib widget dict
        function rectToNumbers(rect) {
            // rect might be:
            // - an array [x1, y1, x2, y2] (plain numbers)
            // - a PDFArray object whose elements are PDFNumber wrappers
            // - an object with {x,y,width,height} (already normalized)
            if (!rect) return null;
            // already normalized structure?
            if (rect.x !== undefined && rect.y !== undefined && rect.width !== undefined && rect.height !== undefined) {
                return {
                    x: rect.x,
                    y: rect.y,
                    width: rect.width,
                    height: rect.height
                };
            }
            // array-like
            const arr = Array.isArray(rect) ? rect : (rect.toArray ? rect.toArray() : null);
            if (!arr) return null;
            const vals = arr.map(v => {
                // pdf-lib PDFNumber objects often have .asNumber or .number property or .toString
                if (typeof v === 'number') return v;
                if (v && typeof v === 'object') {
                    if (typeof v.asNumber === 'function') return v.asNumber();
                    if (typeof v.number === 'number') return v.number;
                    if (typeof v.toString === 'function') return Number(v.toString());
                }
                return Number(v);
            });
            if (vals.length === 4) {
                const [x1, y1, x2, y2] = vals;
                return {
                    x: x1,
                    y: y1,
                    width: x2 - x1,
                    height: y2 - y1
                };
            }
            return null;
        }

        document.getElementById('showFields').addEventListener('click', async () => {
            const fileUrl = window.location.origin + "/files/md.pdf";

            try {
                const res = await fetch(fileUrl);
                if (!res.ok) throw new Error('Failed to fetch PDF: ' + res.statusText);
                const arrayBuffer = await res.arrayBuffer();

                // --- 1) Use pdf-lib to extract field widgets & page sizes ---
                const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
                const form = pdfDoc.getForm ? pdfDoc.getForm() : null;
                const fieldsInfo = []; // will gather {name, pageIndex, rect: {x,y,width,height}}
                if (form) {
                    const fields = form.getFields();
                    for (const field of fields) {
                        const name = field.getName();
                        // pdf-lib: field.acroField.getWidgets() gives widgets
                        const widgets = field.acroField?.getWidgets ? field.acroField.getWidgets() : [];
                        widgets.forEach((widget) => {
                            // widget.getRectangle may exist in some versions
                            let rect = null;
                            try {
                                if (typeof widget.getRectangle === 'function') rect = widget.getRectangle();
                            } catch (e) {
                                rect = null;
                            }
                            if (!rect) {
                                // fallback: read from widget.dict 'Rect'
                                try {
                                    rect = widget.dict?.get ? widget.dict.get('Rect') : null;
                                } catch (e) {
                                    rect = null;
                                }
                            }
                            const normalized = rectToNumbers(rect);
                            // page reference: widget.getPage ? widget.getPage().index : try widget.dict.get('P')
                            let pageIndex = 0;
                            try {
                                // widget.getPage exists sometimes
                                debugger
                                if (typeof widget.getPage === 'function' && widget.getPage()) {
                                    pageIndex = pdfDoc.getPages().indexOf(widget.getPage());
                                } else {
                                    // fallback: try to find a page containing the rect by matching coordinates
                                    pageIndex = 0; // default to page 0; we'll show all fields for page 0 in this example
                                }
                            } catch (_) {
                                pageIndex = 0;
                            }

                            if (normalized) {
                                fieldsInfo.push({
                                    name,
                                    pageIndex,
                                    rect: normalized
                                });
                            } else {
                                // if we couldn't normalize, still add a placeholder
                                fieldsInfo.push({
                                    name,
                                    pageIndex,
                                    rect: {
                                        x: '?',
                                        y: '?',
                                        width: '?',
                                        height: '?'
                                    }
                                });
                            }
                        });
                    }
                } else {
                    console.warn('No PDF form found via pdf-lib.');
                }

                // Also collect page sizes from pdf-lib pages so we know the PDF page width/height in points
                const libPages = pdfDoc.getPages();
                const pageSizes = libPages.map(p => p.getSize()); // { width, height } in PDF points

                // --- 2) Render with PDF.js and overlay the pdf-lib rectangles ---
                const pdfjsDoc = await pdfjsLib.getDocument({
                    data: arrayBuffer
                }).promise;
                const container = document.getElementById('pdfContainer');
                container.innerHTML = ''; // clear old content

                // We'll render all pages that have fields or just the first page for demo.
                // For demo, render pages that exist; if you have many pages, you might render lazily.
                const numPages = pdfjsDoc.numPages;
                for (let pageNumber = 1; pageNumber <= numPages; pageNumber++) {
                    const page = await pdfjsDoc.getPage(pageNumber);
                    const viewportUnscaled = page.getViewport({
                        scale: 1
                    });
                    // Decide target width (fit container or fixed)
                    const wrapper = document.createElement('div');
                    wrapper.className = 'pageWrapper';
                    wrapper.style.width = '100%';
                    wrapper.style.maxWidth = '900px';
                    const canvas = document.createElement('canvas');
                    const overlays = document.createElement('div');
                    overlays.className = 'overlays';

                    wrapper.appendChild(canvas);
                    wrapper.appendChild(overlays);
                    container.appendChild(wrapper);

                    // compute scale that fits container width
                    const containerWidth = Math.min(900, wrapper.clientWidth || 900);
                    // viewportUnscaled.width is page width in PDF points
                    const desiredCssWidth = containerWidth; // CSS pixels
                    const scale = desiredCssWidth / viewportUnscaled.width;
                    const viewport = page.getViewport({
                        scale
                    });

                    // handle high DPI
                    const outputScale = window.devicePixelRatio || 1;
                    canvas.width = Math.floor(viewport.width * outputScale);
                    canvas.height = Math.floor(viewport.height * outputScale);
                    canvas.style.width = viewport.width + 'px'; // css width
                    canvas.style.height = viewport.height + 'px';

                    overlays.style.width = canvas.style.width;
                    overlays.style.height = canvas.style.height;
                    overlays.style.pointerEvents = 'none';

                    const ctx = canvas.getContext('2d');
                    ctx.setTransform(outputScale, 0, 0, outputScale, 0, 0);

                    await page.render({
                        canvasContext: ctx,
                        viewport
                    }).promise;

                    // Place overlays for fields that belong to this page (pageIndex in pdf-lib is 0-based)
                    const pageIndex = pageNumber - 1;
                    const pageFields = fieldsInfo.filter(f => f.pageIndex === pageIndex);

                    // Use PDF page height to convert Y correctly. We can use pageSizes[pageIndex].height
                    const pdfPageHeight = (pageSizes[pageIndex] && pageSizes[pageIndex].height) ||
                        viewportUnscaled.height;

                    // convert and draw boxes
                    pageFields.forEach((f, idx) => {
                        if (!f.rect || f.rect.x === '?') return; // skip invalid rects
                        const {
                            x,
                            y,
                            width,
                            height
                        } = f.rect; // in PDF points
                        // convert to CSS coords (top-left origin)
                        const cssX = x * scale;
                        // viewport.height is CSS height in pixels (not scaled by devicePixelRatio)
                        const cssY = (viewport.height) - ((y + height) * scale);
                        const cssW = width * scale;
                        const cssH = height * scale;

                        const box = document.createElement('div');
                        box.className = 'field-box';
                        box.style.left = cssX + 'px';
                        box.style.top = cssY + 'px';
                        box.style.width = Math.max(2, cssW) + 'px';
                        box.style.height = Math.max(2, cssH) + 'px';
                        box.style.pointerEvents = 'auto'; // allow interactions if you want inputs inside
                        // box.textContent = f.name;

                        // Example: make it an editable input overlay (optional)
                        const input = document.createElement('input');
                        input.className = 'field-input';
                        input.name = f.name;
                        input.type = cssW > 50 ? 'text' : "checkbox";
                        input.placeholder = f.name;
                        // disable pointer events on the overlay container but enable on input by setting pointerEvents=auto on box and input
                        box.appendChild(input);

                        overlays.appendChild(box);
                    });

                    // If you want overlays not to block pointer events for the canvas, set pointer-events appropriately:
                    // overlays.style.pointerEvents = 'none'; and box.style.pointerEvents = 'auto' for interactive fields

                    // For performance: if many pages, render only visible ones (lazy load/virtualize).
                }

                console.log('Done. fieldsInfo:', fieldsInfo);

            } catch (err) {
                alert('Error: ' + err.message);
                console.error(err);
            }
        });
    </script>
</body>

</html>
