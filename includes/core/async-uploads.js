/**
 * Asynchronous Upload Client
 * Handles background image uploads for car listings
 */

class AsyncUploadManager {
    constructor() {
        this.session = this.createSession();
        this.uploadQueue = [];
        this.uploadedImages = new Map(); // filename -> {attachmentId, url, status}
        this.isUploading = false;
        this.maxConcurrentUploads = 3;
        this.currentUploads = 0;
        
        this.initializeEventListeners();
        console.log('[AsyncUpload] Manager initialized with session:', this.session.id);
    }
    
    /**
     * Create new upload session
     */
    createSession() {
        const sessionId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const session = {
            id: sessionId,
            userId: asyncUploads.userId,
            startTime: Date.now(),
            attachmentIds: [],
            status: 'active' // 'active', 'completed', 'cancelled'
        };
        
        // Store in localStorage for persistence
        localStorage.setItem('currentUploadSession', JSON.stringify(session));
        return session;
    }
    
    /**
     * Initialize event listeners for cleanup
     */
    initializeEventListeners() {
        // Cleanup on page unload (when user actually leaves the page)
        window.addEventListener('beforeunload', () => {
            if (this.session.status === 'active' && this.session.attachmentIds.length > 0) {
                // Use navigator.sendBeacon for reliable cleanup
                this.cleanupSessionSync();
            }
        });
        
        // Note: No visibility change cleanup needed - server-side cleanup 
        // handles orphaned uploads after 1 hour, which is sufficient
    }
    
    /**
     * Add file to upload queue and start uploading immediately
     */
    async addFileToQueue(file, originalFile = null) {
        // Create unique key for this file
        const fileKey = this.generateFileKey(originalFile || file);
        
        // Check for duplicates
        if (this.uploadedImages.has(fileKey)) {
            console.log('[AsyncUpload] Duplicate file detected:', file.name);
            throw new Error('Duplicate file: ' + file.name);
        }
        
        // Add to uploaded images with pending status
        this.uploadedImages.set(fileKey, {
            attachmentId: null,
            url: null,
            status: 'pending',
            file: file,
            originalFile: originalFile,
            uploadProgress: 0
        });
        
        // Start upload immediately
        this.uploadFile(fileKey, file, originalFile);
        
        return fileKey;
    }
    
    /**
     * Generate unique key for file (for duplicate detection)
     */
    generateFileKey(file) {
        return file.name + '_' + file.size + '_' + file.type;
    }
    
    /**
     * Upload single file to server
     */
    async uploadFile(fileKey, file, originalFile = null) {
        if (this.currentUploads >= this.maxConcurrentUploads) {
            // Add to queue if at max concurrent uploads
            this.uploadQueue.push({ fileKey, file, originalFile });
            return;
        }
        
        this.currentUploads++;
        const imageData = this.uploadedImages.get(fileKey);
        
        try {
            console.log('[AsyncUpload] Starting upload for:', file.name);
            
            // Update status
            imageData.status = 'uploading';
            this.updateUploadProgress(fileKey, 0);
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'async_upload_image');
            formData.append('nonce', asyncUploads.nonce);
            formData.append('session_id', this.session.id);
            formData.append('original_filename', originalFile ? originalFile.name : file.name);
            formData.append('form_type', this.getFormType());
            formData.append('image', file);
            
            // Upload with progress tracking
            const response = await this.uploadWithProgress(formData, fileKey);
            
            if (response.success) {
                // Update image data with server response
                imageData.attachmentId = response.data.attachment_id;
                imageData.url = response.data.attachment_url;
                imageData.status = 'completed';
                imageData.uploadProgress = 100;
                
                // Add to session tracking
                this.session.attachmentIds.push(response.data.attachment_id);
                this.updateSessionStorage();
                
                console.log('[AsyncUpload] Upload completed:', file.name, 'Attachment ID:', response.data.attachment_id);
                
                // Trigger success callback
                this.onUploadSuccess(fileKey, response.data);
                
            } else {
                throw new Error(response.data.message || 'Upload failed');
            }
            
        } catch (error) {
            console.error('[AsyncUpload] Upload failed:', file.name, error);
            
            // Update status
            imageData.status = 'failed';
            imageData.error = error.message;
            
            // Trigger error callback
            this.onUploadError(fileKey, error);
            
        } finally {
            this.currentUploads--;
            
            // Process next item in queue
            if (this.uploadQueue.length > 0) {
                const next = this.uploadQueue.shift();
                this.uploadFile(next.fileKey, next.file, next.originalFile);
            }
        }
    }
    
    /**
     * Upload with progress tracking
     */
    uploadWithProgress(formData, fileKey) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            // Track upload progress
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const progress = Math.round((event.loaded / event.total) * 100);
                    this.updateUploadProgress(fileKey, progress);
                }
            };
            
            xhr.onload = () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (error) {
                    reject(new Error('Invalid server response'));
                }
            };
            
            xhr.onerror = () => {
                reject(new Error('Network error'));
            };
            
            xhr.open('POST', asyncUploads.ajaxUrl);
            xhr.send(formData);
        });
    }
    
    /**
     * Remove uploaded image
     */
    async removeImage(fileKey) {
        const imageData = this.uploadedImages.get(fileKey);
        if (!imageData || !imageData.attachmentId) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_async_image');
            formData.append('nonce', asyncUploads.nonce);
            formData.append('session_id', this.session.id);
            formData.append('attachment_id', imageData.attachmentId);
            
            const response = await fetch(asyncUploads.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Remove from tracking
                this.uploadedImages.delete(fileKey);
                
                // Remove from session
                const index = this.session.attachmentIds.indexOf(imageData.attachmentId);
                if (index > -1) {
                    this.session.attachmentIds.splice(index, 1);
                    this.updateSessionStorage();
                }
                
                console.log('[AsyncUpload] Image removed:', imageData.attachmentId);
                this.onImageRemoved(fileKey);
                
            } else {
                throw new Error(result.data.message || 'Remove failed');
            }
            
        } catch (error) {
            console.error('[AsyncUpload] Remove failed:', error);
            throw error;
        }
    }
    
    /**
     * Get all uploaded attachment IDs for form submission
     */
    getUploadedAttachmentIds() {
        return this.session.attachmentIds.filter(id => id !== null);
    }
    
    /**
     * Mark session as completed (preserve files)
     */
    markSessionCompleted() {
        this.session.status = 'completed';
        this.updateSessionStorage();
        console.log('[AsyncUpload] Session marked as completed');
    }
    
    /**
     * Clean up session (delete all pending uploads)
     */
    async cleanupSession() {
        if (this.session.status === 'completed') {
            return; // Don't cleanup completed sessions
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'cleanup_upload_session');
            formData.append('nonce', asyncUploads.nonce);
            formData.append('session_id', this.session.id);
            
            const response = await fetch(asyncUploads.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('[AsyncUpload] Session cleaned up:', result.data.deleted_count, 'files');
            }
            
        } catch (error) {
            console.error('[AsyncUpload] Cleanup failed:', error);
        } finally {
            // Clear local data
            this.uploadedImages.clear();
            this.session.attachmentIds = [];
            this.session.status = 'cancelled';
            localStorage.removeItem('currentUploadSession');
        }
    }
    
    /**
     * Synchronous cleanup for page unload
     */
    cleanupSessionSync() {
        if (this.session.status === 'completed') {
            return;
        }
        
        const data = new URLSearchParams();
        data.append('action', 'cleanup_upload_session');
        data.append('nonce', asyncUploads.nonce);
        data.append('session_id', this.session.id);
        
        // Use sendBeacon for reliable delivery
        navigator.sendBeacon(asyncUploads.ajaxUrl, data);
        
        console.log('[AsyncUpload] Session cleanup beacon sent');
    }
    
    /**
     * Update session in localStorage
     */
    updateSessionStorage() {
        localStorage.setItem('currentUploadSession', JSON.stringify(this.session));
    }
    
    /**
     * Get current form type
     */
    getFormType() {
        if (document.getElementById('add-car-listing-form')) {
            return 'add_listing';
        } else if (document.getElementById('edit-car-listing-form')) {
            return 'edit_listing';
        }
        return 'unknown';
    }
    
    /**
     * Update upload progress (override in implementation)
     */
    updateUploadProgress(fileKey, progress) {
        console.log('[AsyncUpload] Progress for', fileKey, ':', progress + '%');
        // Override this method to update UI
    }
    
    /**
     * Callback for successful upload (override in implementation)
     */
    onUploadSuccess(fileKey, data) {
        console.log('[AsyncUpload] Upload success callback for:', fileKey);
        // Override this method to update UI
    }
    
    /**
     * Callback for upload error (override in implementation)
     */
    onUploadError(fileKey, error) {
        console.log('[AsyncUpload] Upload error callback for:', fileKey, error);
        // Override this method to update UI
    }
    
    /**
     * Callback for image removal (override in implementation)
     */
    onImageRemoved(fileKey) {
        console.log('[AsyncUpload] Image removed callback for:', fileKey);
        // Override this method to update UI
    }
}

// Make class available globally
window.AsyncUploadManager = AsyncUploadManager; 