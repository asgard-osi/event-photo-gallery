# 📸 EventGallery – Photo Upload & Share

![License](https://img.shields.io/badge/License-MIT-green.svg)  
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)  
![Responsive](https://img.shields.io/badge/Mobile-Friendly-brightgreen.svg)  
![Status](https://img.shields.io/badge/Status-Active-success.svg)

EventGallery is a **self-hosted event photo sharing system** built with PHP.  
It allows guests at weddings, parties, or other events to **upload their photos easily** and view them in a **modern, responsive gallery**.  

---

## ✨ Features

- 📤 **Easy Photo Upload** – Guests can upload multiple images from phone or desktop.  
- 🔑 **Password Protection** – The gallery is protected by a simple password.  
- 📝 **Author Attribution** – Each photo can be linked to the uploader’s name.  
- 🖼 **Optimized Images** – Automatic generation of thumbnails and display images.  
- 🕒 **Smart Sorting** – Sort by EXIF capture time or upload time (newest/oldest).  
- 📂 **Multiple Sections** – Organize photos into event sections (e.g., *Photobooth*, *Ceremony*).  
- 💡 **Lightbox Viewer** – Fullscreen preview with navigation & author captions.  
- ⬇ **Download Originals** – Guests can download the original full-size images.  
- 📱 **Mobile-Friendly** – Optimized layout for phones and tablets.  
- ⏱ **Session Handling** – Author sessions last up to 72h.  

---

## 🖼 Screenshots

*(replace with real screenshots of your site)*

### Upload Page
![Upload Example](docs/screenshots/upload.png)

### Gallery View
![Gallery Example](docs/screenshots/gallery.png)

---

## 📂 Project Structure

event-photo-gallery/
├── config.php # Configuration (password, titles, folders, etc.)
├── index.php # Main upload page
├── galerie.php # Gallery page with lightbox
├── upload.php # Handles file uploads & author attribution
├── thumbs.php # Thumbnail & display image generator
├── session_author.php # Manages session for uploader names
├── uploads/ # Uploaded photos
│ ├── _thumbs/ # Thumbnails (auto-generated)
│ ├── _display/ # Display-size images (auto-generated)
│ └── _authors.csv # Author ↔ photo mapping
└── ...

---

## ⚙️ Configuration

Open **`config.php`** and adjust to your needs:

```php
// Gallery password
$GALLERY_PASSWORD = 'YourSecurePassword';

// Site title & subtitle (used on index page)
$SITE_TITLE    = 'EventGallery';
$SITE_SUBTITLE = 'EventGallery - Photo Upload & Share';

// Extra folders under /uploads/ displayed as separate gallery sections
$EXTRA_FOLDERS = [
    'Photobooth',
    'Ceremony',
    'Party',
];

---

## 🚀 Installation

Upload all files to your PHP-enabled web server.

Ensure the uploads/ directory is writable by the web server.

Adjust settings in config.php.

Open index.php in your browser.

Share the link with your guests 🎉

---

## 🖥 Usage

Guests open index.php to upload photos.

Uploaded photos are processed into:

_thumbs/ → small preview thumbnails

_display/ → optimized images for gallery view

originals stay in uploads/

Authors’ names are stored in _authors.csv and shown in the lightbox view.

The gallery is accessible via galerie.php (password-protected).

---

## 🔐 Security Notes

⚠️ This project is intended for private events only.

It uses simple password protection (not individual accounts).

Do not use for sensitive or public-facing deployments without further hardening.

Always set a strong password in config.php.

---

## 🛠 Requirements

PHP 7.4+ with GD/EXIF extensions enabled

Apache / Nginx web server (or compatible)

Write permissions for uploads/ directory

---

## 📜 License

This project is licensed under the MIT License
.
You are free to use, modify, and share.

---

## ❤️ Credits

Developed for private event use – weddings, parties & more.
The goal: make photo sharing easy, fun, and beautiful for guests.
