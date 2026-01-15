/**
 * Image Optimization Utilities
 * Shared functions for compressing and optimizing images before upload
 */

class ImageOptimizer {
    constructor(options = {}) {
        // PRODUCTION SAFETY: Only log in development environments
        this.isDevelopment = window.location.hostname === 'localhost' || 
                            window.location.hostname.includes('staging') ||
                            window.location.search.includes('debug=true');
        
        this.maxWidth = options.maxWidth || 1920;
        this.maxHeight = options.maxHeight || 1080;
        this.quality = options.quality || 0.8;
        this.maxFileSize = options.maxFileSize || 12288; // 12MB in KB - minimal client processing
        this.allowedTypes = options.allowedTypes || ['image/jpeg', 'image/jfif', 'image/pjpeg', 'image/png', 'image/webp'];
        
        // Check browser compatibility
        this.isSupported = this.checkBrowserSupport();
    }

    /**
     * Check if the browser supports canvas and required features
     * @returns {boolean} - Whether optimization is supported
     */
    checkBrowserSupport() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            return !!(canvas && ctx && canvas.toBlob && File && FileReader);
        } catch (error) {
            if (this.isDevelopment) console.warn('[ImageOptimizer] Browser compatibility check failed:', error);
            return false;
        }
    }

    /**
     * Compress and resize an image file
     * @param {File} file - The image file to optimize
     * @returns {Promise<File>} - The optimized image file
     */
    async optimizeImage(file) {
        return new Promise((resolve, reject) => {
            // Check browser support
            if (!this.isSupported) {
                if (this.isDevelopment) console.warn('[ImageOptimizer] Browser does not support optimization, using original file');
                resolve(file);
                return;
            }

            // Check if file type is allowed
            if (!this.allowedTypes.includes(file.type)) {
                reject(new Error(`File type ${file.type} not allowed`));
                return;
            }

            // For car listings, skip client-side processing for best quality
            // Only resize very large files to reduce upload time (keeping original format)
            if (file.size <= (12 * 1024 * 1024)) { // 12MB threshold
                if (this.isDevelopment) console.log(`[ImageOptimizer] File ${file.name} will be processed on server for best quality`);
                resolve(file);
                return;
            }

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            // Set up timeout
            const timeout = setTimeout(() => {
                if (this.isDevelopment) console.warn(`[ImageOptimizer] Optimization timeout for ${file.name}, using original`);
                resolve(file);
            }, 30000); // 30 second timeout

            img.onload = () => {
                try {
                    clearTimeout(timeout);
                    
                    // Calculate new dimensions while maintaining aspect ratio
                    const { width, height } = this.calculateDimensions(img.width, img.height);
                    
                    canvas.width = width;
                    canvas.height = height;

                    // Draw and compress the image
                    ctx.fillStyle = '#FFFFFF'; // White background for transparency
                    ctx.fillRect(0, 0, width, height);
                    ctx.drawImage(img, 0, 0, width, height);

                    // Keep original format when resizing to preserve quality
                    const outputFormat = file.type;
                    const quality = outputFormat === 'image/png' ? 1.0 : this.quality; // PNG is lossless
                    
                    canvas.toBlob(
                        (blob) => {
                            if (blob && blob.size > 0) {
                                // Create a new file maintaining original format
                                const optimizedFile = new File([blob], file.name, {
                                    type: outputFormat, // Keep original format for best quality
                                    lastModified: Date.now()
                                });

                                // Only use optimized version if it's actually smaller
                                if (optimizedFile.size < file.size) {
                                    if (this.isDevelopment) console.log(`[ImageOptimizer] Image resized: ${file.name} (kept as ${outputFormat})`);
                                    if (this.isDevelopment) console.log(`[ImageOptimizer] Original size: ${(file.size / 1024).toFixed(2)} KB`);
                                    if (this.isDevelopment) console.log(`[ImageOptimizer] Resized size: ${(optimizedFile.size / 1024).toFixed(2)} KB`);
                                    if (this.isDevelopment) console.log(`[ImageOptimizer] Size reduction: ${((1 - optimizedFile.size / file.size) * 100).toFixed(1)}%`);
                                    resolve(optimizedFile);
                                } else {
                                    if (this.isDevelopment) console.log(`[ImageOptimizer] Resize didn't reduce size for ${file.name}, using original`);
                                    resolve(file);
                                }
                            } else {
                                if (this.isDevelopment) console.warn(`[ImageOptimizer] Failed to resize ${file.name}, using original`);
                                resolve(file);
                            }
                        },
                        outputFormat, // Keep original format to preserve quality
                        quality
                    );
                } catch (error) {
                    clearTimeout(timeout);
                    if (this.isDevelopment) console.error(`[ImageOptimizer] Error during optimization of ${file.name}:`, error);
                    resolve(file); // Fallback to original file
                }
            };

            img.onerror = () => {
                clearTimeout(timeout);
                if (this.isDevelopment) console.error(`[ImageOptimizer] Failed to load image ${file.name}, using original`);
                resolve(file); // Fallback to original file
            };

            try {
                img.src = URL.createObjectURL(file);
            } catch (error) {
                clearTimeout(timeout);
                if (this.isDevelopment) console.error(`[ImageOptimizer] Failed to create object URL for ${file.name}:`, error);
                resolve(file); // Fallback to original file
            }
        });
    }

    /**
     * Calculate optimal dimensions while maintaining aspect ratio
     * @param {number} originalWidth
     * @param {number} originalHeight
     * @returns {Object} - New width and height
     */
    calculateDimensions(originalWidth, originalHeight) {
        let { width, height } = { width: originalWidth, height: originalHeight };

        // If image is larger than max dimensions, scale it down
        if (width > this.maxWidth || height > this.maxHeight) {
            const aspectRatio = width / height;

            if (width > height) {
                width = this.maxWidth;
                height = width / aspectRatio;
            } else {
                height = this.maxHeight;
                width = height * aspectRatio;
            }
        }

        return {
            width: Math.round(width),
            height: Math.round(height)
        };
    }

    /**
     * Optimize multiple images
     * @param {FileList|Array} files - Array of image files
     * @param {Function} progressCallback - Callback for progress updates
     * @returns {Promise<Array>} - Array of optimized files
     */
    async optimizeImages(files, progressCallback = null) {
        const optimizedFiles = [];
        const totalFiles = files.length;

        for (let i = 0; i < totalFiles; i++) {
            try {
                const optimizedFile = await this.optimizeImage(files[i]);
                optimizedFiles.push(optimizedFile);

                if (progressCallback) {
                    progressCallback({
                        completed: i + 1,
                        total: totalFiles,
                        currentFile: files[i].name,
                        percentage: Math.round(((i + 1) / totalFiles) * 100)
                    });
                }
            } catch (error) {
                if (this.isDevelopment) console.error(`Failed to optimize image ${files[i].name}:`, error);
                // Add original file if optimization fails
                optimizedFiles.push(files[i]);
                
                if (progressCallback) {
                    progressCallback({
                        completed: i + 1,
                        total: totalFiles,
                        currentFile: files[i].name,
                        percentage: Math.round(((i + 1) / totalFiles) * 100),
                        error: error.message
                    });
                }
            }
        }

        return optimizedFiles;
    }

    /**
     * Create a visual preview of file size savings
     * @param {File} originalFile
     * @param {File} optimizedFile
     * @returns {Object} - Size comparison data
     */
    getCompressionStats(originalFile, optimizedFile) {
        const originalSizeKB = originalFile.size / 1024;
        const optimizedSizeKB = optimizedFile.size / 1024;
        const compressionRatio = ((originalFile.size - optimizedFile.size) / originalFile.size) * 100;

        return {
            originalSize: originalSizeKB.toFixed(2),
            optimizedSize: optimizedSizeKB.toFixed(2),
            savedSize: (originalSizeKB - optimizedSizeKB).toFixed(2),
            compressionRatio: compressionRatio.toFixed(1)
        };
    }
}

// Export for use in other files
window.ImageOptimizer = ImageOptimizer; 