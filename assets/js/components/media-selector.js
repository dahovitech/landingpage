/**
 * MediaSelector - Composant pour les champs de sélection de médias dans les formulaires
 */
(function($) {
    'use strict';

    class MediaSelector {
        constructor(element, options = {}) {
            this.element = $(element);
            this.options = $.extend({
                multiple: true,
                showPreview: true,
                allowUpload: true
            }, options);

            this.selectedMedias = [];
            this.mediaPicker = null;

            this.init();
        }

        init() {
            this.parseExistingSelection();
            this.createUI();
            this.bindEvents();
            this.hideOriginalSelect();
        }

        parseExistingSelection() {
            // Récupérer les médias déjà sélectionnés
            const selectedValues = this.element.val();
            if (selectedValues && selectedValues.length > 0) {
                // Faire un appel AJAX pour récupérer les détails des médias sélectionnés
                this.loadSelectedMediaDetails(selectedValues);
            }
        }

        async loadSelectedMediaDetails(mediaIds) {
            try {
                const promises = mediaIds.map(id => 
                    $.get(`/admin/media/list?id=${id}&limit=1`)
                );
                const responses = await Promise.all(promises);
                
                this.selectedMedias = responses
                    .map(response => response.medias[0])
                    .filter(media => media); // Filtrer les undefined
                
                this.updateUI();
            } catch (error) {
                console.error('Erreur lors du chargement des médias sélectionnés:', error);
            }
        }

        createUI() {
            const container = $(`
                <div class="media-selector-container">
                    <div class="media-selector-preview" id="preview_${this.element.attr('id')}">
                        <!-- Les médias sélectionnés seront affichés ici -->
                    </div>
                    <div class="media-selector-actions mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="select_${this.element.attr('id')}">
                            <i class="bi bi-images me-2"></i>Sélectionner des médias
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="clear_${this.element.attr('id')}" style="display: none;">
                            <i class="bi bi-x-circle me-2"></i>Effacer
                        </button>
                    </div>
                </div>
            `);

            this.element.after(container);
            this.container = container;
            this.previewContainer = container.find('.media-selector-preview');
            this.selectBtn = container.find(`#select_${this.element.attr('id')}`);
            this.clearBtn = container.find(`#clear_${this.element.attr('id')}`);
        }

        hideOriginalSelect() {
            this.element.hide();
        }

        bindEvents() {
            this.selectBtn.on('click', () => {
                this.openMediaPicker();
            });

            this.clearBtn.on('click', () => {
                this.clearSelection();
            });
        }

        openMediaPicker() {
            if (!this.mediaPicker) {
                // Créer un élément temporaire pour le MediaPicker
                const tempElement = $('<div>').appendTo('body');
                
                this.mediaPicker = new MediaPicker(tempElement, {
                    multiple: this.options.multiple,
                    showUpload: this.options.allowUpload,
                    onSelect: (medias) => {
                        this.setSelection(Array.isArray(medias) ? medias : [medias]);
                    }
                });
            }
            
            this.mediaPicker.show();
        }

        setSelection(medias) {
            this.selectedMedias = medias;
            this.updateOriginalSelect();
            this.updateUI();
        }

        clearSelection() {
            this.selectedMedias = [];
            this.updateOriginalSelect();
            this.updateUI();
        }

        updateOriginalSelect() {
            if (this.options.multiple) {
                // Désélectionner toutes les options
                this.element.find('option').prop('selected', false);
                
                // Sélectionner les nouvelles options
                this.selectedMedias.forEach(media => {
                    this.element.find(`option[value="${media.id}"]`).prop('selected', true);
                });
            } else {
                const mediaId = this.selectedMedias.length > 0 ? this.selectedMedias[0].id : '';
                this.element.val(mediaId);
            }
        }

        updateUI() {
            this.previewContainer.empty();
            
            if (this.selectedMedias.length === 0) {
                this.previewContainer.html(`
                    <div class="text-muted text-center py-3" style="border: 2px dashed #dee2e6; border-radius: 0.5rem;">
                        <i class="bi bi-images" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">Aucun média sélectionné</p>
                    </div>
                `);
                this.clearBtn.hide();
            } else {
                const previews = this.selectedMedias.map(media => this.createPreviewItem(media));
                this.previewContainer.html(`
                    <div class="row g-2">
                        ${previews.join('')}
                    </div>
                `);
                this.clearBtn.show();

                // Bind remove events
                this.previewContainer.find('.remove-media').on('click', (e) => {
                    const mediaId = parseInt($(e.currentTarget).data('media-id'));
                    this.removeMedia(mediaId);
                });
            }

            // Mettre à jour le texte du bouton
            const btnText = this.selectedMedias.length === 0 
                ? 'Sélectionner des médias'
                : `Modifier la sélection (${this.selectedMedias.length})`;
            this.selectBtn.html(`<i class="bi bi-images me-2"></i>${btnText}`);
        }

        createPreviewItem(media) {
            const isImage = media.isImage;
            const preview = isImage 
                ? `<img src="${media.url}" alt="${media.alt}" style="width: 100%; height: 80px; object-fit: cover;">`
                : `<div class="d-flex align-items-center justify-content-center" style="height: 80px; background: #f8f9fa;">
                     <i class="bi bi-file-earmark text-muted" style="font-size: 1.5rem;"></i>
                   </div>`;

            return `
                <div class="col-md-2 col-sm-3 col-4">
                    <div class="media-preview-item" style="position: relative; border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden;">
                        ${preview}
                        <button type="button" class="btn btn-danger btn-sm remove-media" 
                                data-media-id="${media.id}"
                                style="position: absolute; top: 2px; right: 2px; width: 24px; height: 24px; padding: 0; border-radius: 50%;">
                            <i class="bi bi-x" style="font-size: 0.7rem;"></i>
                        </button>
                        <div class="p-2" style="background: #f8f9fa;">
                            <small class="text-truncate d-block">${media.alt || media.fileName}</small>
                        </div>
                    </div>
                </div>
            `;
        }

        removeMedia(mediaId) {
            this.selectedMedias = this.selectedMedias.filter(media => media.id !== mediaId);
            this.updateOriginalSelect();
            this.updateUI();
        }

        destroy() {
            if (this.mediaPicker) {
                this.mediaPicker.destroy();
            }
            this.container.remove();
            this.element.show();
        }
    }

    // Plugin jQuery
    $.fn.mediaSelector = function(options) {
        return this.each(function() {
            if (!$(this).data('mediaSelector')) {
                $(this).data('mediaSelector', new MediaSelector(this, options));
            }
        });
    };

    // Auto-initialisation pour les selects avec la classe 'media-selector'
    $(document).ready(function() {
        $('select.media-selector').each(function() {
            const $select = $(this);
            const multiple = $select.data('multiple') === 'true';
            const showPreview = $select.data('show-preview') !== 'false';
            const allowUpload = $select.data('allow-upload') !== 'false';

            $select.mediaSelector({
                multiple: multiple,
                showPreview: showPreview,
                allowUpload: allowUpload
            });
        });
    });

    // Expose la classe globalement
    window.MediaSelector = MediaSelector;

})(jQuery);
