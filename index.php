<!DOCTYPE html>
<html>
<head>
    <title>MetaScrub - Drag & Drop Metadata Cleaner</title>
    <link rel="stylesheet" href="style.css">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="icons/css/all.min.css">
</head>
<body>
    <h2><i class="fas fa-broom"></i> MetaScrub</h2>
    <p>Drag & drop or click to upload supported image formats (JPG, PNG, WEBP, TIFF, BMP, AVIF, HEIC)</p>

    <form id="upload-form" action="process.php" method="post" enctype="multipart/form-data">
        <div id="drop-zone">
            Drop files here or click to upload
            <input type="file" name="image" id="file-input" style="display:none"
                accept=".jpg,.jpeg,.png,.webp,.bmp,.tiff,.avif,.heic">
        </div>

        <p id="file-name-display" class="file-info"></p>

        <button type="submit"><i class="fas fa-rocket"></i> Scrub Metadata</button>
    </form>

    <div id="preview"></div>

    <footer>
        <p>Created by <strong>Shoaib Tamboli</strong> | 12/04/2025</p>
        <p>
            <a href="https://github.com/yourgithub" target="_blank"><i class="fab fa-github"></i> GitHub</a> |
            <a href="https://www.linkedin.com/in/yourlinkedin" target="_blank"><i class="fab fa-linkedin"></i> LinkedIn</a>
        </p>
    </footer>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const fileNameDisplay = document.getElementById('file-name-display');
        const form = document.getElementById('upload-form');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            updateFileName();
        });

        fileInput.addEventListener('change', updateFileName);

        function updateFileName() {
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                fileNameDisplay.innerText = `📁 You uploaded: ${fileName}`;
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const res = await fetch('process.php', {
                method: 'POST',
                body: formData
            });
            const html = await res.text();
            document.getElementById('preview').innerHTML = html;
        });
    </script>

    <script>
        function toggleDescription(button) {
            const descBox = button.closest('tr').querySelector('.description-box');
            if (descBox) {
                descBox.style.display = (descBox.style.display === 'none' || descBox.style.display === '') ? 'block' : 'none';
            }
        }

        function toggleAllDescriptions() {
            const descriptionColumns = document.querySelectorAll('.description-column');
            descriptionColumns.forEach((col) => {
                col.style.display = col.style.display === 'none' ? 'table-cell' : 'none';
            });
        }
    </script>
</body>
</html>
