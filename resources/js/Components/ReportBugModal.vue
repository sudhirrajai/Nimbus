<script setup>
import { ref } from 'vue';
import axios from 'axios';
import html2canvas from 'html2canvas';

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['close']);

const message = ref('');
const attachScreenshot = ref(true);
const additionalImages = ref([]);
const sending = ref(false);
const error = ref(null);
const success = ref(false);

const fileInputRef = ref(null);

const triggerFileInput = () => {
    fileInputRef.value.click();
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    addFiles(files);
};

const handleFileDrop = (event) => {
    const files = Array.from(event.dataTransfer.files);
    addFiles(files);
};

const addFiles = (files) => {
    const validImages = files.filter(file => file.type.startsWith('image/'));
    additionalImages.value = [...additionalImages.value, ...validImages];
};

const removeFile = (index) => {
    additionalImages.value.splice(index, 1);
};

const closeModal = () => {
    if (sending.value) return;
    message.value = '';
    attachScreenshot.value = true;
    additionalImages.value = [];
    error.value = null;
    success.value = false;
    emit('close');
};

const submitReport = async () => {
    if (!message.value.trim()) {
        error.value = 'Please describe the bug or feedback before submitting.';
        return;
    }

    sending.value = true;
    error.value = null;
    success.value = false;

    try {
        let screenshotFile = null;

        if (attachScreenshot.value) {
            // Briefly wait to ensure visual stability
            await new Promise(r => setTimeout(r, 100));

            // Capture the document body excluding the modal container itself
            const canvas = await html2canvas(document.body, {
                useCORS: true,
                logging: false,
                scale: 1.2,
                scrollX: 0,
                scrollY: 0,
                windowWidth: document.documentElement.clientWidth,
                windowHeight: document.documentElement.clientHeight,
            });

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            if (blob) {
                screenshotFile = new File([blob], 'screenshot.png', { type: 'image/png' });
            }
        }

        const formData = new FormData();
        formData.append('message', message.value);

        if (screenshotFile) {
            formData.append('screenshot', screenshotFile);
        }

        additionalImages.value.forEach((file, index) => {
            formData.append(`images[${index}]`, file);
        });

        const response = await axios.post('/bug-reports', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            }
        });

        if (response.data.status) {
            success.value = true;
            message.value = '';
            additionalImages.value = [];
        } else {
            error.value = response.data.message || 'Failed to submit bug report.';
        }
    } catch (err) {
        console.error('Bug report error:', err);
        error.value = err.response?.data?.message || 'An error occurred while sending report. Please check server connections.';
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <Transition name="fade">
        <div v-if="isOpen" class="custom-modal-overlay" data-html2canvas-ignore="true" @click.self="closeModal">
            <Transition name="scale">
                <div v-if="isOpen" class="custom-modal-container bg-white border-radius-xl shadow-2xl">
                    <!-- Modal Header -->
                    <div class="custom-modal-header border-bottom border-gray-200">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-rounded text-emerald text-lg">bug_report</span>
                            <h5 class="mb-0 font-weight-bold text-dark text-md">Report an Issue</h5>
                        </div>
                        <button class="btn-close-custom" @click="closeModal" :disabled="sending">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="custom-modal-body">
                        <!-- Success Alert -->
                        <div v-if="success" class="alert-custom success-alert animate-fade">
                            <span class="material-symbols-rounded text-md">check_circle</span>
                            <div class="alert-content">
                                <p class="mb-0 text-sm font-weight-bold">Thank you!</p>
                                <p class="mb-0 text-xs text-secondary-custom">Your bug report has been successfully transmitted to the VmCoreCentral dashboard.</p>
                            </div>
                        </div>

                        <!-- Error Alert -->
                        <div v-if="error" class="alert-custom error-alert animate-fade">
                            <span class="material-symbols-rounded text-md">error</span>
                            <div class="alert-content">
                                <p class="mb-0 text-xs font-weight-bold">{{ error }}</p>
                            </div>
                        </div>

                        <div v-if="!success" class="space-y-custom">
                            <!-- Message text area -->
                            <div class="form-group-custom">
                                <label class="label-custom font-weight-bold text-xs text-secondary uppercase tracking-wider">Bug Description / Message</label>
                                <textarea 
                                    v-model="message"
                                    rows="4"
                                    placeholder="Please describe the issue in detail. What happened? How can we reproduce it?"
                                    class="form-control-custom"
                                    :disabled="sending"
                                ></textarea>
                            </div>

                            <!-- Live Screenshot Checkbox -->
                            <label class="checkbox-container-custom">
                                <input type="checkbox" v-model="attachScreenshot" :disabled="sending" />
                                <span class="checkmark-custom"></span>
                                <span class="checkbox-label text-sm text-dark font-medium d-flex align-items-center gap-1.5">
                                    <span class="material-symbols-rounded text-sm">photo_camera</span>
                                    Attach live screenshot of the current page
                                </span>
                            </label>

                            <!-- Drag & Drop Upload Area -->
                            <div class="form-group-custom">
                                <label class="label-custom font-weight-bold text-xs text-secondary uppercase tracking-wider">Additional Images (Optional)</label>
                                <div 
                                    @click="triggerFileInput"
                                    @dragover.prevent
                                    @drop.prevent="handleFileDrop"
                                    class="upload-drop-area border-dashed"
                                    :class="{ 'disabled': sending }"
                                >
                                    <input 
                                        type="file" 
                                        ref="fileInputRef" 
                                        accept="image/*" 
                                        multiple 
                                        @change="handleFileSelect"
                                        class="d-none"
                                        :disabled="sending"
                                    />
                                    <span class="material-symbols-rounded text-secondary-custom text-xl">cloud_upload</span>
                                    <p class="mb-0 text-xs text-secondary font-weight-bold mt-1">Drag & drop files here, or <span class="text-emerald hover-underline cursor-pointer">browse</span></p>
                                    <p class="mb-0 text-[10px] text-muted">Supports PNG, JPG, JPEG (Max 10MB per file)</p>
                                </div>
                            </div>

                            <!-- File Previews -->
                            <div v-if="additionalImages.length > 0" class="file-previews-container mt-2">
                                <span class="text-[10px] font-bold text-secondary uppercase tracking-wider block mb-1.5">Selected Files ({{ additionalImages.length }})</span>
                                <div class="file-previews-grid">
                                    <div 
                                        v-for="(file, idx) in additionalImages" 
                                        :key="idx" 
                                        class="file-preview-card border"
                                    >
                                        <span class="material-symbols-rounded text-secondary-custom text-sm">image</span>
                                        <span class="file-preview-name text-xs truncate">{{ file.name }}</span>
                                        <button 
                                            type="button" 
                                            @click.stop="removeFile(idx)"
                                            class="btn-remove-file"
                                            :disabled="sending"
                                        >
                                            <span class="material-symbols-rounded">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="custom-modal-footer border-top border-gray-200">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="btn-custom btn-secondary-custom" 
                            :disabled="sending"
                        >
                            {{ success ? 'Close' : 'Cancel' }}
                        </button>
                        <button 
                            v-if="!success"
                            type="button" 
                            @click="submitReport" 
                            class="btn-custom btn-emerald" 
                            :disabled="sending"
                        >
                            <span v-if="sending" class="spinner-custom me-1"></span>
                            {{ sending ? 'Submitting...' : 'Submit Report' }}
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
/* Modal overlay styling */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* Modal container */
.custom-modal-container {
    width: 100%;
    max-width: 500px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: 90vh;
}

/* Header, Body, Footer structures */
.custom-modal-header {
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.custom-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex-grow: 1;
}

.custom-modal-footer {
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}

/* Typography and utilities */
.text-emerald {
    color: #10b981;
}
.btn-close-custom {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.btn-close-custom:hover {
    color: #4b5563;
    background-color: #f3f4f6;
}

.space-y-custom > * + * {
    margin-top: 20px;
}

/* Form components styling */
.form-group-custom {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.label-custom {
    font-size: 10px;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.form-control-custom {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 12px;
    font-size: 14px;
    color: #1f2937;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    resize: none;
}
.form-control-custom:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2);
}

/* Custom Checkbox */
.checkbox-container-custom {
    display: flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    user-select: none;
    padding-left: 28px;
    margin-bottom: 0;
}
.checkbox-container-custom input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.checkmark-custom {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 0;
    height: 18px;
    width: 18px;
    background-color: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    transition: all 0.2s;
}
.checkbox-container-custom:hover input ~ .checkmark-custom {
    border-color: #10b981;
}
.checkbox-container-custom input:checked ~ .checkmark-custom {
    background-color: #10b981;
    border-color: #10b981;
}
.checkmark-custom:after {
    content: "";
    position: absolute;
    display: none;
}
.checkbox-container-custom input:checked ~ .checkmark-custom:after {
    display: block;
}
.checkbox-container-custom .checkmark-custom:after {
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Upload drop area */
.upload-drop-area {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    background-color: #f9fafb;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.upload-drop-area:hover {
    border-color: #10b981;
    background-color: #f0fdf4;
}
.upload-drop-area.disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* File Previews */
.file-previews-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 120px;
    overflow-y: auto;
    padding-right: 4px;
}
.file-preview-card {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    gap: 8px;
}
.file-preview-name {
    flex-grow: 1;
    color: #374151;
    font-weight: 500;
}
.btn-remove-file {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 2px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-remove-file:hover {
    color: #ef4444;
    background-color: #fee2e2;
}

/* Custom buttons */
.btn-custom {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-secondary-custom {
    background-color: #fff;
    border: 1px solid #d1d5db;
    color: #374151;
}
.btn-secondary-custom:hover {
    background-color: #f3f4f6;
    border-color: #c5c7cb;
}
.btn-emerald {
    background-color: #10b981;
    border: 1px solid #10b981;
    color: #fff;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
}
.btn-emerald:hover {
    background-color: #059669;
    border-color: #059669;
}
.btn-custom:disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* Custom Alert classes */
.alert-custom {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    align-items: flex-start;
}
.success-alert {
    background-color: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}
.error-alert {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.spinner-custom {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #white;
    animation: spin-custom 0.8s linear infinite;
}

@keyframes spin-custom {
    to { transform: rotate(360deg); }
}

/* Animations */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

.scale-enter-active, .scale-leave-active {
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s ease;
}
.scale-enter-from, .scale-leave-to {
    transform: scale(0.95);
    opacity: 0;
}
</style>
