import { ref } from 'vue';

export function useImageViewer() {
    const isViewerOpen = ref(false);
    const viewerImages = ref([]);
    const viewerActiveIndex = ref(0);
    const viewerTitle = ref('');
    const viewerSubtitle = ref('');

    /**
     * Membuka modal image viewer
     * 
     * @param {Array} images - Array of objects [{ url: '...' }]
     * @param {Number} index - Indeks gambar aktif (default: 0)
     * @param {String} title - Judul konteks (opsional)
     * @param {String} subtitle - Subjudul / metadata (opsional)
     */
    const openViewer = (images, index = 0, title = '', subtitle = '') => {
        viewerImages.value = images;
        viewerActiveIndex.value = index;
        viewerTitle.value = title;
        viewerSubtitle.value = subtitle;
        isViewerOpen.value = true;
        
        // Lock body scroll
        document.body.style.overflow = 'hidden';
    };

    const closeViewer = () => {
        isViewerOpen.value = false;
        
        // Unlock body scroll
        document.body.style.overflow = '';
        
        // Delay clearing data untuk memberi waktu animasi close selesai
        setTimeout(() => {
            viewerImages.value = [];
            viewerActiveIndex.value = 0;
            viewerTitle.value = '';
            viewerSubtitle.value = '';
        }, 300);
    };

    return {
        isViewerOpen,
        viewerImages,
        viewerActiveIndex,
        viewerTitle,
        viewerSubtitle,
        openViewer,
        closeViewer
    };
}
