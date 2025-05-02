Install Ghostscript & LibreOffice 
    https://www.ghostscript.com/download/gsdnld.html    
    (Download-> Install Setup -> Set Environmental Variable use below given command after opening powershell as admin)
    [Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\Program Files\gs\gs10.01.1\bin", "Machine")

    https://www.libreoffice.org/download/download/
    (Download-> Install Setup -> Set Environmental Variable use below given command after opening powershell as admin)
    [Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\Program Files\LibreOffice\program", "Machine")

    https://exiftool.org/
    (Download-> Extract )

check both version shows :
    gswin64c -v
    soffice --version


If using Xampp to run php than
    Enable GD Extension in XAMPP
    Access from the XAMPP Control Panel:
    Click Config next to Apache
        Choose php.ini
        ctr+f -> ;extension=gd
                 extension=gd   (Uncomment it by removing ; )
Stop Apache , Restart than run Index.php



Run using:
http://localhost/Metadata Scrubber/index.php




Created By Shoaib Tamboli
22-04-2025










Project Info:

| Tool/Library     | Purpose                                                                 |
|------------------|-------------------------------------------------------------------------|
| PHP              | Core backend logic (file upload, processing)                           |
| GD Library       | Remove and rewrite images without EXIF metadata (JPEG/PNG)             |
| ExifTool         | Extract & remove metadata from modern image formats                    |
| ImageMagick      | Applies blur filter to remove watermark traces                         |
| Ghostscript      | Strip metadata from PDF files                                           |
| LibreOffice      | Re-generate DOCX files without hidden metadata                         |



| File Type  | Extensions                    | Tools Used                |
|------------|-------------------------------|---------------------------|
| Images     | JPG, JPEG, PNG, BMP, TIFF     | PHP GD, ExifTool          |
| Modern Img | WebP, AVIF, HEIC, HEIF        | ExifTool                  |
| Documents  | PDF                           | Ghostscript               |
| Documents  | DOCX                          | LibreOffice (headless)    |


Working:
1. User uploads a file Supported format only.
2. PHP backend:
    Extracts metadata using ExifTool or PHP built-ins
    Optionally removes GPS, EXIF, watermark
    Recompresses or re-exports file
3. Logs metadata comparison and generates download links


Logs:
    logs/original_metadata.txt – Before cleaning
    logs/cleaned_metadata.txt – After cleaning
    logs/updated_metadata.txt – Comparison & removed tags

