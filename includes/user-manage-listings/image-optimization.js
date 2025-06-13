/**
 * Image Optimization Utilities
 * Shared functions for compressing and optimizing images before upload
 */

class ImageOptimizer {
    constructor(options = {}) {
        this.maxWidth = options.maxWidth || 1920;
        this.maxHeight = options.maxHeight || 1080;
        this.quality = options.quality || 0.8;
        this.maxFileSize = options.maxFileSize || 2048; // 2MB in KB
        this.allowedTypes = options.allowedTypes || ['image/jpeg', 'image/png', 'image/webp'];
        
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
            console.warn('[ImageOptimizer] Browser compatibility check failed:', error);
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
                console.warn('[ImageOptimizer] Browser does not support optimization, using original file');
                resolve(file);
                return;
            }

            // Check if file type is allowed
            if (!this.allowedTypes.includes(file.type)) {
                reject(new Error(`File type ${file.type} not allowed`));
                return;
            }

            // If file is already small enough, don't optimize
            if (file.size <= (this.maxFileSize * 1024)) {
                console.log(`[ImageOptimizer] File ${file.name} is already optimized (${(file.size / 1024).toFixed(1)}KB)`);
                resolve(file);
                return;
            }

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            // Set up timeout
            const timeout = setTimeout(() => {
                console.warn(`[ImageOptimizer] Optimization timeout for ${file.name}, using original`);
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

                    canvas.toBlob(
                        (blob) => {
                            if (blob && blob.size > 0) {
                                // Create a new file from the blob
                                const optimizedFile = new File([blob], file.name, {
                                    type: 'image/jpeg', // Always output as JPEG for better compression
                                    lastModified: Date.now()
                                });

                                // Only use optimized version if it's actually smaller
                                if (optimizedFile.size < file.size) {
                                    console.log(`[ImageOptimizer] Image optimized: ${file.name}`);
                                    console.log(`[ImageOptimizer] Original size: ${(file.size / 1024).toFixed(2)} KB`);
                                    console.log(`[ImageOptimizer] Optimized size: ${(optimizedFile.size / 1024).toFixed(2)} KB`);
                                    console.log(`[ImageOptimizer] Compression ratio: ${((1 - optimizedFile.size / file.size) * 100).toFixed(1)}%`);
                                    resolve(optimizedFile);
                                } else {
                                    console.log(`[ImageOptimizer] Optimization didn't reduce size for ${file.name}, using original`);
                                    resolve(file);
                                }
                            } else {
                                console.warn(`[ImageOptimizer] Failed to compress ${file.name}, using original`);
                                resolve(file);
                            }
                        },
                        'image/jpeg', // Always output as JPEG for better compression
                        this.quality
                    );
                } catch (error) {
                    clearTimeout(timeout);
                    console.error(`[ImageOptimizer] Error during optimization of ${file.name}:`, error);
                    resolve(file); // Fallback to original file
                }
            };

            img.onerror = () => {
                clearTimeout(timeout);
                console.error(`[ImageOptimizer] Failed to load image ${file.name}, using original`);
                resolve(file); // Fallback to original file
            };

            try {
                img.src = URL.createObjectURL(file);
            } catch (error) {
                clearTimeout(timeout);
                console.error(`[ImageOptimizer] Failed to create object URL for ${file.name}:`, error);
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
                console.error(`Failed to optimize image ${files[i].name}:`, error);
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