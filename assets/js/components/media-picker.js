/**
 * MediaPicker - Composant jQuery pour la sélection de médias
 * Utilisable dans les formulaires et l'éditeur de texte
 */
(function($) {
    'use strict';

    class MediaPicker {
        constructor(element, options = {}) {
            this.element = $(element);
            this.options = $.extend({
                multiple: false,
                types: ['image', 'video', 'audio', 'document'], // Types autorisés
                onSelect: null, // Callback lors de la sélection
                onUpload: null, // Callback après upload
                insertMode: false, // Mode insertion pour l'éditeur
                showUpload: true, // Afficher l'onglet upload
                maxSelection: null // Limite de sélection (null = illimité)
            }, options);

            this.selectedMedias = [];
            this.currentPage = 1;
            this.searchQuery = '';
            this.typeFilter = '';
            this.modalId = 'mediaPicker_' + Date.now();

            this.init();
        }

        init() {
            this.createModal();
            this.bindEvents();
        }

        createModal() {
            const modal = $(`
                <div class="modal fade" id="${this.modalId}" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-images me-2"></i>Sélectionner des médias
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <!-- Tabs Navigation -->
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="browse-tab" data-bs-toggle="tab" 
                                                data-bs-target="#browse-pane" type="button" role="tab">
                                            <i class="bi bi-collection me-2"></i>Parcourir
                                        </button>
                                    </li>
                                    ${this.options.showUpload ? `
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="upload-tab" data-bs-toggle="tab" 
                                                data-bs-target="#upload-pane" type="button" role="tab">
                                            <i class="bi bi-cloud-upload me-2"></i>Uploader
                                        </button>
                                    </li>
                                    ` : ''}
                                </ul>

                                <!-- Tab Content -->
                                <div class="tab-content">
                                    <!-- Browse Tab -->
                                    <div class="tab-pane fade show active p-3" id="browse-pane" role="tabpanel">
                                        <!-- Filters -->
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" id="mp-search" 
                                                       placeholder="Rechercher un média...">
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select" id="mp-type-filter">
                                                    <option value="">Tous les types</option>
                                                    <option value="image">Images</option>
                                                    <option value="video">Vidéos</option>
                                                    <option value="audio">Audio</option>
                                                    <option value="document">Documents</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Media Grid -->
                                        <div id="mp-media-grid" class="row g-2" style="max-height: 400px; overflow-y: auto;">
                                            <!-- Les médias seront chargés ici -->
                                        </div>

                                        <!-- Pagination -->
                                        <div id="mp-pagination" class="d-flex justify-content-center mt-3">
                                            <!-- Pagination sera générée ici -->
                                        </div>
                                    </div>

                                    <!-- Upload Tab -->
                                    ${this.options.showUpload ? `
                                    <div class="tab-pane fade p-3" id="upload-pane" role="tabpanel">
                                        <div class="upload-zone text-center py-5" id="mp-upload-zone">
                                            <i class="bi bi-cloud-upload text-primary" style="font-size: 3rem;"></i>
                                            <h6 class="mt-3">Glissez-déposez vos fichiers ici</h6>
                                            <p class="text-muted mb-3">ou</p>
                                            <button type="button" class="btn btn-outline-primary" id="mp-select-files">
                                                Sélectionner des fichiers
                                            </button>
                                            <input type="file" id="mp-file-input" multiple 
                                                   accept="image/*,video/*,audio/*,.pdf" style="display: none;">
                                        </div>

                                        <div id="mp-upload-progress" style="display: none;">
                                            <h6>Upload en cours...</h6>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>

                                        <div id="mp-upload-results" class="mt-3"></div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="me-auto">
                                    <span id="mp-selection-count" class="text-muted"></span>
                                </div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-primary" id="mp-confirm-selection" disabled>
                                    ${this.options.insertMode ? 'Insérer' : 'Sélectionner'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            this.modal = modal;
        }

        bindEvents() {
            const modal = this.modal;

            // Search
            modal.find('#mp-search').on('input', (e) => {
                this.searchQuery = e.target.value;
                this.currentPage = 1;
                this.loadMedias();
            });

            // Type filter
            modal.find('#mp-type-filter').on('change', (e) => {
                this.typeFilter = e.target.value;
                this.currentPage = 1;
                this.loadMedias();
            });

            // Upload events
            if (this.options.showUpload) {
                modal.find('#mp-select-files').on('click', () => {
                    modal.find('#mp-file-input').click();
                });

                modal.find('#mp-file-input').on('change', (e) => {
                    this.handleFiles(e.target.files);
                });

                // Drag and drop
                modal.find('#mp-upload-zone')
                    .on('dragover dragenter', (e) => {
                        e.preventDefault();
                        $(e.currentTarget).addClass('dragover');
                    })
                    .on('dragleave dragend drop', (e) => {
                        e.preventDefault();
                        $(e.currentTarget).removeClass('dragover');
                    })
                    .on('drop', (e) => {
                        const files = e.originalEvent.dataTransfer.files;
                        this.handleFiles(files);
                    });
            }

            // Confirm selection
            modal.find('#mp-confirm-selection').on('click', () => {
                this.confirmSelection();
            });

            // Load medias when browse tab is shown
            modal.find('#browse-tab').on('shown.bs.tab', () => {
                this.loadMedias();
            });

            // Load initial medias when modal is shown
            modal.on('shown.bs.modal', () => {
                this.selectedMedias = [];
                this.updateSelectionUI();
                this.loadMedias();
            });
        }

        show() {
            this.modal.modal('show');
        }

        hide() {
            this.modal.modal('hide');
        }

        async loadMedias() {
            try {
                const response = await $.get('/admin/media/list', {
                    page: this.currentPage,
                    search: this.searchQuery,
                    type: this.typeFilter
                });

                this.renderMedias(response.medias);
                this.renderPagination(response.pagination);
            } catch (error) {
                console.error('Erreur lors du chargement des médias:', error);
            }
        }

        renderMedias(medias) {
            const grid = this.modal.find('#mp-media-grid');
            grid.empty();

            if (medias.length === 0) {
                grid.html(`
                    <div class="col-12 text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2">Aucun média trouvé</p>
                    </div>
                `);
                return;
            }

            medias.forEach(media => {
                const item = this.createMediaItem(media);
                grid.append(item);
            });
        }

        createMediaItem(media) {
            const isSelected = this.selectedMedias.some(m => m.id === media.id);
            const isImage = media.isImage;
            
            const preview = isImage 
                ? `<img src="${media.url}" alt="${media.alt}" style="width: 100%; height: 100px; object-fit: cover;">`
                : `<div class="d-flex align-items-center justify-content-center h-100" style="height: 100px;">
                     <i class="bi bi-file-earmark text-muted" style="font-size: 2rem;"></i>
                   </div>`;

            const item = $(`
                <div class="col-lg-2 col-md-3 col-4">
                    <div class="media-picker-item ${isSelected ? 'selected' : ''}" 
                         data-media-id="${media.id}" style="cursor: pointer; border: 2px solid ${isSelected ? '#0d6efd' : '#dee2e6'}; border-radius: 0.5rem; overflow: hidden; position: relative;">
                        ${preview}
                        ${isSelected ? '<div class="position-absolute top-0 end-0 p-1"><i class="bi bi-check-circle-fill text-primary"></i></div>' : ''}
                        <div class="p-2" style="background: #f8f9fa;">
                            <small class="text-truncate d-block">${media.alt || media.fileName}</small>
                        </div>
                    </div>
                </div>
            `);

            item.find('.media-picker-item').on('click', () => {
                this.toggleMediaSelection(media, item);
            });

            return item;
        }

        toggleMediaSelection(media, itemElement) {
            const isSelected = this.selectedMedias.some(m => m.id === media.id);

            if (isSelected) {
                // Désélectionner
                this.selectedMedias = this.selectedMedias.filter(m => m.id !== media.id);
                itemElement.find('.media-picker-item')
                    .removeClass('selected')
                    .css('border-color', '#dee2e6')
                    .find('.bi-check-circle-fill').parent().remove();
            } else {
                // Sélectionner
                if (!this.options.multiple) {
                    // Mode simple : désélectionner les autres
                    this.selectedMedias = [];
                    this.modal.find('.media-picker-item').each(function() {
                        $(this).removeClass('selected')
                               .css('border-color', '#dee2e6')
                               .find('.bi-check-circle-fill').parent().remove();
                    });
                }

                // Vérifier la limite de sélection
                if (this.options.maxSelection && this.selectedMedias.length >= this.options.maxSelection) {
                    this.showToast('Limite de sélection atteinte', 'warning');
                    return;
                }

                this.selectedMedias.push(media);
                itemElement.find('.media-picker-item')
                    .addClass('selected')
                    .css('border-color', '#0d6efd')
                    .append('<div class="position-absolute top-0 end-0 p-1"><i class="bi bi-check-circle-fill text-primary"></i></div>');
            }

            this.updateSelectionUI();
        }

        updateSelectionUI() {
            const count = this.selectedMedias.length;
            const countText = count === 0 ? '' : 
                             count === 1 ? '1 média sélectionné' : 
                             `${count} médias sélectionnés`;
            
            this.modal.find('#mp-selection-count').text(countText);
            this.modal.find('#mp-confirm-selection').prop('disabled', count === 0);
        }

        confirmSelection() {
            if (this.selectedMedias.length === 0) return;

            if (this.options.onSelect) {
                this.options.onSelect(this.options.multiple ? this.selectedMedias : this.selectedMedias[0]);
            }

            this.hide();
        }

        async handleFiles(files) {
            if (files.length === 0) return;

            const progressContainer = this.modal.find('#mp-upload-progress');
            const resultsContainer = this.modal.find('#mp-upload-results');
            
            progressContainer.show();
            resultsContainer.empty();

            const progressBar = progressContainer.find('.progress-bar');
            let completed = 0;

            for (let file of files) {
                try {
                    const result = await this.uploadFile(file);
                    if (result.success) {
                        resultsContainer.append(`
                            <div class="alert alert-success">
                                <strong>${file.name}</strong> uploadé avec succès
                            </div>
                        `);

                        if (this.options.onUpload) {
                            this.options.onUpload(result.media);
                        }
                    } else {
                        resultsContainer.append(`
                            <div class="alert alert-danger">
                                <strong>${file.name}</strong>: ${result.message}
                            </div>
                        `);
                    }
                } catch (error) {
                    resultsContainer.append(`
                        <div class="alert alert-danger">
                            <strong>${file.name}</strong>: Erreur d'upload
                        </div>
                    `);
                }

                completed++;
                const progress = (completed / files.length) * 100;
                progressBar.css('width', progress + '%');
            }

            setTimeout(() => {
                progressContainer.hide();
                // Recharger la liste des médias
                if (this.modal.find('#browse-tab').hasClass('active')) {
                    this.loadMedias();
                }
            }, 1000);
        }

        uploadFile(file) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', file);

                $.ajax({
                    url: '/admin/media/upload',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: resolve,
                    error: reject
                });
            });
        }

        renderPagination(pagination) {
            const container = this.modal.find('#mp-pagination');
            container.empty();

            if (pagination.total <= 1) return;

            const nav = $('<nav><ul class="pagination pagination-sm"></ul></nav>');
            const ul = nav.find('ul');

            // Page précédente
            if (pagination.current > 1) {
                ul.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${pagination.current - 1}">Précédent</a>
                    </li>
                `);
            }

            // Pages numérotées
            for (let i = Math.max(1, pagination.current - 2); i <= Math.min(pagination.total, pagination.current + 2); i++) {
                ul.append(`
                    <li class="page-item ${i === pagination.current ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `);
            }

            // Page suivante
            if (pagination.current < pagination.total) {
                ul.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${pagination.current + 1}">Suivant</a>
                    </li>
                `);
            }

            // Event listeners pour la pagination
            ul.find('a[data-page]').on('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt($(e.target).data('page'));
                this.loadMedias();
            });

            container.append(nav);
        }

        showToast(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-info';
            const toast = $(`
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);

            $('body').append(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        destroy() {
            this.modal.remove();
        }
    }

    // Plugin jQuery
    $.fn.mediaPicker = function(options) {
        return this.each(function() {
            if (!$(this).data('mediaPicker')) {
                $(this).data('mediaPicker', new MediaPicker(this, options));
            }
        });
    };

    // Expose la classe globalement
    window.MediaPicker = MediaPicker;

})(jQuery);
