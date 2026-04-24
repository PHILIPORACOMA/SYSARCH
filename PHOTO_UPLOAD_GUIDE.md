# Student Photo Upload Feature

## Overview
Students can now upload and manage their profile photos directly from the student dashboard.

## Features
- **Upload Photo**: Click the "Upload Photo" button to select and upload an image
- **Remove Photo**: If a photo is uploaded, click "Remove Photo" to delete it
- **Photo Formats**: Supports JPG, PNG, and GIF files
- **File Size Limit**: Maximum 5MB per image
- **Auto Replacement**: Uploading a new photo automatically replaces the old one

## Installation

### Step 1: Initialize Database
Before using the photo feature, run the initialization script to add the PhotoPath column:

1. Open your browser and navigate to:
   ```
   http://localhost/SYSARCH/init_photo_column.php
   ```

2. You should see confirmation messages that the PhotoPath column was added and the uploads directory was created.

### Step 2: Permissions
Ensure the `uploads` directory is writable:
- Windows: The directory should have write permissions for the web server (Apache)
- Linux/Mac: Set permissions: `chmod 755 uploads/`

## How to Use

### Uploading a Photo
1. Go to Student Dashboard
2. In the Profile Card section, click **"Upload Photo"** button
3. Select an image file (JPG, PNG, or GIF, max 5MB)
4. Wait for the success message and the page will refresh automatically
5. Your profile photo will replace the initial avatar

### Removing a Photo
1. Go to Student Dashboard
2. In the Profile Card section, click **"Remove Photo"** button (only visible if photo exists)
3. Confirm the deletion
4. The page will refresh and show the initial avatar again

## Technical Details

### Database
- **Table**: `students_info`
- **Column**: `PhotoPath` (VARCHAR 255)
- **Storage**: File path relative to project root

### File Storage
- **Location**: `uploads/student_photos/`
- **Naming**: `{StudentID}_{Timestamp}.{extension}`
- **Security**: Directory listing is disabled, only accessible via direct links

### Validation
- **Allowed MIME Types**: image/jpeg, image/png, image/gif
- **Maximum File Size**: 5MB
- **File Replacement**: Old photos are automatically deleted when new ones are uploaded

## Troubleshooting

**Issue**: "Upload failed" error
- Check file size (max 5MB)
- Verify file format (JPG, PNG, GIF only)
- Ensure `uploads` directory has write permissions

**Issue**: Photo not displaying
- Verify the `uploads/student_photos/` directory exists
- Check that the PhotoPath column was added to the database
- Ensure Apache has read permissions for the file

**Issue**: PhotoPath column not found
- Run `init_photo_column.php` to create the column

## Security Notes
- File type is validated on both client and server side
- File size limits prevent abuse
- Directory listing is disabled via index.php files
- Files are stored outside the public web directory structure
