/**
 * CustomEditor - Éditeur de texte personnalisé avec intégration média
 * Transforme les champs TextareaType en éditeur riche
 */
(function($) {
    'use strict';

    class CustomEditor {
        constructor(element, options = {}) {
            this.element = $(element);
            this.options = $.extend({
                height: 300,
                placeholder: 'Tapez votre contenu ici...',
                enableMedia: true,
                enableFormatting: true,
                enableLinks: true,
                toolbar: [
                    'bold', 'italic', 'underline', 'strikethrough', '|',
                    'h1', 'h2', 'h3', 'paragraph', '|',
                    'link', 'unlink', '|',
                    'image', 'media', '|',
                    'unorderedList', 'orderedList', '|',
                    'removeFormat', 'undo', 'redo'
                ],
                onChange: null
            }, options);

            this.editorId = 'editor_' + Date.now();
            this.content = this.element.val() || '';
            this.mediaPicker = null;

            this.init();
        }

        init() {
            this.createEditor();
            this.bindEvents();
            this.hideOriginalTextarea();
        }

        createEditor() {
            const toolbar = this.createToolbar();
            const editor = $(`
                <div class="custom-editor" id="${this.editorId}">
                    ${toolbar}
                    <div class="editor-content" contenteditable="true" 
                         style="min-height: ${this.options.height}px; padding: 15px; border: 1px solid #dee2e6; border-top: none; outline: none; overflow-y: auto;"
                         placeholder="${this.options.placeholder}">
                        ${this.content}
                    </div>
                </div>
            `);

            this.element.after(editor);
            this.editorElement = editor;
            this.contentElement = editor.find('.editor-content');

            // Initialiser le MediaPicker si activé
            if (this.options.enableMedia) {
                this.initMediaPicker();
            }
        }

        createToolbar() {
            const toolbar = $('<div class="editor-toolbar" style="border: 1px solid #dee2e6; background: #f8f9fa; padding: 8px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center;"></div>');

            this.options.toolbar.forEach(item => {
                if (item === '|') {
                    toolbar.append('<div class="toolbar-separator" style="width: 1px; height: 24px; background: #dee2e6; margin: 0 4px;"></div>');
                } else {
                    const button = this.createToolbarButton(item);
                    toolbar.append(button);
                }
            });

            return toolbar.prop('outerHTML');
        }

        createToolbarButton(type) {
            const buttons = {
                bold: { icon: 'bi-type-bold', title: 'Gras', command: 'bold' },
                italic: { icon: 'bi-type-italic', title: 'Italique', command: 'italic' },
                underline: { icon: 'bi-type-underline', title: 'Souligné', command: 'underline' },
                strikethrough: { icon: 'bi-type-strikethrough', title: 'Barré', command: 'strikeThrough' },
                h1: { icon: 'bi-type-h1', title: 'Titre 1', command: 'formatBlock', value: 'h1' },
                h2: { icon: 'bi-type-h2', title: 'Titre 2', command: 'formatBlock', value: 'h2' },
                h3: { icon: 'bi-type-h3', title: 'Titre 3', command: 'formatBlock', value: 'h3' },
                paragraph: { icon: 'bi-paragraph', title: 'Paragraphe', command: 'formatBlock', value: 'p' },
                link: { icon: 'bi-link', title: 'Lien', command: 'createLink' },
                unlink: { icon: 'bi-link-45deg', title: 'Supprimer le lien', command: 'unlink' },
                unorderedList: { icon: 'bi-list-ul', title: 'Liste à puces', command: 'insertUnorderedList' },
                orderedList: { icon: 'bi-list-ol', title: 'Liste numérotée', command: 'insertOrderedList' },
                removeFormat: { icon: 'bi-eraser', title: 'Supprimer le formatage', command: 'removeFormat' },
                undo: { icon: 'bi-arrow-counterclockwise', title: 'Annuler', command: 'undo' },
                redo: { icon: 'bi-arrow-clockwise', title: 'Refaire', command: 'redo' },
                image: { icon: 'bi-image', title: 'Insérer une image', command: 'insertImage' },
                media: { icon: 'bi-collection', title: 'Insérer un média', command: 'insertMedia' }
            };

            const config = buttons[type];
            if (!config) return $('<span></span>');

            return $(`
                <button type="button" class="btn btn-sm btn-outline-secondary toolbar-btn" 
                        data-command="${config.command}" 
                        ${config.value ? `data-value="${config.value}"` : ''}
                        title="${config.title}">
                    <i class="bi ${config.icon}"></i>
                </button>
            `);
        }

        hideOriginalTextarea() {
            this.element.hide();
        }

        bindEvents() {
            const editor = this.editorElement;
            const content = this.contentElement;

            // Événements de la barre d'outils
            editor.find('.toolbar-btn').on('click', (e) => {
                e.preventDefault();
                this.executeCommand($(e.currentTarget));
            });

            // Événements du contenu
            content.on('input', () => {
                this.updateOriginalTextarea();
                if (this.options.onChange) {
                    this.options.onChange(this.getContent());
                }
            });

            content.on('paste', (e) => {
                setTimeout(() => {
                    this.cleanPastedContent();
                    this.updateOriginalTextarea();
                }, 10);
            });

            // Placeholder
            content.on('focus blur input', () => {
                this.updatePlaceholder();
            });

            // Prévenir l'envoi du formulaire sur Entrée dans certains cas
            content.on('keydown', (e) => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    document.execCommand('insertText', false, '    ');
                }
            });

            this.updatePlaceholder();
        }

        executeCommand(button) {
            const command = button.data('command');
            const value = button.data('value');

            this.contentElement.focus();

            switch (command) {
                case 'createLink':
                    this.insertLink();
                    break;
                case 'insertImage':
                    this.insertImageDialog();
                    break;
                case 'insertMedia':
                    this.insertMediaDialog();
                    break;
                default:
                    if (value) {
                        document.execCommand(command, false, value);
                    } else {
                        document.execCommand(command, false, null);
                    }
                    break;
            }

            this.updateOriginalTextarea();
        }

        insertLink() {
            const selection = window.getSelection();
            const selectedText = selection.toString();
            const url = prompt('Entrez l\'URL du lien:', 'https://');
            
            if (url && url !== 'https://') {
                if (selectedText) {
                    document.execCommand('createLink', false, url);
                } else {
                    const linkText = prompt('Texte du lien:', url);
                    if (linkText) {
                        document.execCommand('insertHTML', false, `<a href="${url}">${linkText}</a>`);
                    }
                }
            }
        }

        insertImageDialog() {
            const url = prompt('Entrez l\'URL de l\'image:', 'https://');
            if (url && url !== 'https://') {
                const alt = prompt('Texte alternatif (optionnel):', '');
                document.execCommand('insertHTML', false, `<img src="${url}" alt="${alt}" style="max-width: 100%; height: auto;">`);
            }
        }

        insertMediaDialog() {
            if (!this.mediaPicker) {
                this.initMediaPicker();
            }
            this.mediaPicker.show();
        }

        initMediaPicker() {
            // Créer un élément temporaire pour le MediaPicker
            const tempElement = $('<div>').appendTo('body');
            
            this.mediaPicker = new MediaPicker(tempElement, {
                multiple: true,
                insertMode: true,
                onSelect: (medias) => {
                    this.insertMedias(Array.isArray(medias) ? medias : [medias]);
                }
            });
        }

        insertMedias(medias) {
            let html = '';
            
            medias.forEach(media => {
                if (media.isImage) {
                    html += `<img src="${media.url}" alt="${media.alt || ''}" style="max-width: 100%; height: auto; margin: 10px 0;">`;
                } else {
                    // Pour les autres types de médias, créer un lien de téléchargement
                    html += `<p><a href="${media.url}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-2"></i>${media.alt || media.fileName}
                    </a></p>`;
                }
            });

            document.execCommand('insertHTML', false, html);
            this.updateOriginalTextarea();
        }

        cleanPastedContent() {
            // Nettoyer le contenu collé en supprimant les styles indésirables
            const content = this.contentElement;
            content.find('*').each(function() {
                const element = $(this);
                // Garder seulement certains attributs
                const allowedAttrs = ['href', 'src', 'alt', 'title'];
                const attrs = this.attributes;
                for (let i = attrs.length - 1; i >= 0; i--) {
                    const attr = attrs[i];
                    if (!allowedAttrs.includes(attr.name)) {
                        element.removeAttr(attr.name);
                    }
                }
            });
        }

        updatePlaceholder() {
            const content = this.contentElement;
            const isEmpty = content.text().trim() === '' && content.find('img, video, audio').length === 0;
            
            if (isEmpty) {
                content.attr('data-placeholder', this.options.placeholder);
                if (!content.hasClass('empty')) {
                    content.addClass('empty');
                    // Ajouter les styles CSS pour le placeholder
                    if (!$('#custom-editor-styles').length) {
                        $('<style id="custom-editor-styles">')
                            .text(`
                                .editor-content.empty:before {
                                    content: attr(data-placeholder);
                                    color: #6c757d;
                                    pointer-events: none;
                                }
                            `)
                            .appendTo('head');
                    }
                }
            } else {
                content.removeClass('empty');
            }
        }

        updateOriginalTextarea() {
            const html = this.contentElement.html();
            this.element.val(html);
        }

        getContent() {
            return this.contentElement.html();
        }

        setContent(html) {
            this.contentElement.html(html);
            this.updateOriginalTextarea();
            this.updatePlaceholder();
        }

        insertHTML(html) {
            document.execCommand('insertHTML', false, html);
            this.updateOriginalTextarea();
        }

        focus() {
            this.contentElement.focus();
        }

        destroy() {
            if (this.mediaPicker) {
                this.mediaPicker.destroy();
            }
            this.editorElement.remove();
            this.element.show();
        }
    }

    // Plugin jQuery
    $.fn.customEditor = function(options) {
        return this.each(function() {
            if (!$(this).data('customEditor')) {
                $(this).data('customEditor', new CustomEditor(this, options));
            }
        });
    };

    // Auto-initialisation pour les textareas avec la classe 'custom-editor'
    $(document).ready(function() {
        $('textarea.custom-editor').customEditor();
    });

    // Expose la classe globalement
    window.CustomEditor = CustomEditor;

})(jQuery);
