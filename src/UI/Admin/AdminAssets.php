<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminAssets
{
    public static function enqueue(string $hookSuffix, string $pageHook, array $approvedFiles): void
    {
        if ($pageHook !== $hookSuffix) {
            return;
        }

        $adminCssPath = FMB_PATH . 'assets/css/admin.css';
        $adminCssVersion = file_exists($adminCssPath) ? (string) filemtime($adminCssPath) : FMB_VERSION;

        wp_enqueue_style(
            'fmb-admin',
            FMB_URL . 'assets/css/admin.css',
            [],
            $adminCssVersion
        );

        wp_register_script('fmb-admin-upload', '', [], FMB_VERSION, true);
        wp_enqueue_script('fmb-admin-upload');
        wp_add_inline_script(
            'fmb-admin-upload',
            'window.fmbApprovedImportFiles = ' . wp_json_encode($approvedFiles) . ";
            document.addEventListener('DOMContentLoaded', function () {
                var uploadForm = document.querySelector('.fmb-upload-form');
                var fileInput = document.getElementById('fmb_medal_file');
                var submitButton = document.getElementById('fmb_generate_preview');
                var duplicateConfirmationInput = document.getElementById('fmb_duplicate_import_confirmed');
                var approvedFiles = Array.isArray(window.fmbApprovedImportFiles) ? window.fmbApprovedImportFiles : [];
                var approvedFileLookup = approvedFiles.map(function (fileName) {
                    return String(fileName).toLowerCase();
                });
                var modal = null;
                var modalTitle = null;
                var modalMessage = null;
                var modalConfirmButton = null;
                var modalCancelButton = null;
                var modalCloseButton = null;
                var modalResolver = null;
                var previousFocus = null;

                var createConfirmationModal = function () {
                    if (modal) {
                        return;
                    }

                    modal = document.createElement('div');
                    modal.className = 'fmb-confirm-modal';
                    modal.setAttribute('role', 'dialog');
                    modal.setAttribute('aria-modal', 'true');
                    modal.setAttribute('aria-labelledby', 'fmb-confirm-modal-title');
                    modal.setAttribute('aria-describedby', 'fmb-confirm-modal-message');
                    modal.hidden = true;
                    modal.innerHTML = '<div class=\"fmb-confirm-modal__backdrop\" data-fmb-confirm-cancel></div>' +
                        '<div class=\"fmb-confirm-modal__panel\">' +
                        '<button type=\"button\" class=\"fmb-confirm-modal__close\" data-fmb-confirm-cancel aria-label=\"" . esc_js(__('Cerrar', 'cannes-festival-medal-tracker')) . "\">&times;</button>' +
                        '<h2 id=\"fmb-confirm-modal-title\"></h2>' +
                        '<p id=\"fmb-confirm-modal-message\"></p>' +
                        '<div class=\"fmb-confirm-modal__actions\">' +
                        '<button type=\"button\" class=\"button\" data-fmb-confirm-cancel>" . esc_js(__('Cancelar', 'cannes-festival-medal-tracker')) . "</button>' +
                        '<button type=\"button\" class=\"button button-primary\" data-fmb-confirm-accept></button>' +
                        '</div>' +
                        '</div>';

                    document.body.appendChild(modal);
                    modalTitle = modal.querySelector('#fmb-confirm-modal-title');
                    modalMessage = modal.querySelector('#fmb-confirm-modal-message');
                    modalConfirmButton = modal.querySelector('[data-fmb-confirm-accept]');
                    modalCancelButton = modal.querySelector('[data-fmb-confirm-cancel]');
                    modalCloseButton = modal.querySelector('.fmb-confirm-modal__close');

                    modal.addEventListener('click', function (event) {
                        if (event.target.hasAttribute('data-fmb-confirm-cancel')) {
                            closeConfirmationModal(false);
                        }
                    });

                    modalConfirmButton.addEventListener('click', function () {
                        closeConfirmationModal(true);
                    });

                    document.addEventListener('keydown', function (event) {
                        if (!modal || modal.hidden) {
                            return;
                        }

                        if (event.key === 'Escape') {
                            closeConfirmationModal(false);
                        }
                    });
                };

                var closeConfirmationModal = function (confirmed) {
                    if (!modal || modal.hidden) {
                        return;
                    }

                    modal.hidden = true;
                    document.body.classList.remove('fmb-confirm-modal-open');

                    if (previousFocus && typeof previousFocus.focus === 'function') {
                        previousFocus.focus();
                    }

                    if (modalResolver) {
                        modalResolver(confirmed);
                        modalResolver = null;
                    }
                };

                var requestConfirmation = function (options) {
                    createConfirmationModal();
                    previousFocus = document.activeElement;
                    modalTitle.textContent = options.title || '" . esc_js(__('Confirmar accion', 'cannes-festival-medal-tracker')) . "';
                    modalMessage.textContent = options.message || '';
                    modalConfirmButton.textContent = options.button || '" . esc_js(__('Confirmar', 'cannes-festival-medal-tracker')) . "';
                    modalConfirmButton.className = options.variant === 'danger'
                        ? 'button button-primary fmb-confirm-modal__button-danger'
                        : 'button button-primary';
                    modal.hidden = false;
                    document.body.classList.add('fmb-confirm-modal-open');
                    modalConfirmButton.focus();

                    return new Promise(function (resolve) {
                        modalResolver = resolve;
                    });
                };

                document.querySelectorAll('form[data-fmb-confirm]').forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.dataset.fmbConfirmApproved === '1') {
                            form.dataset.fmbConfirmApproved = '0';
                            return;
                        }

                        event.preventDefault();
                        requestConfirmation({
                            title: form.getAttribute('data-fmb-confirm-title'),
                            message: form.getAttribute('data-fmb-confirm'),
                            button: form.getAttribute('data-fmb-confirm-button'),
                            variant: form.getAttribute('data-fmb-confirm-variant')
                        }).then(function (confirmed) {
                            if (!confirmed) {
                                return;
                            }

                            form.dataset.fmbConfirmApproved = '1';

                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                                return;
                            }

                            form.submit();
                        });
                    });
                });

                if (!uploadForm || !fileInput || !submitButton || !duplicateConfirmationInput) {
                    return;
                }

                var selectedFileName = function () {
                    if (!fileInput.files || fileInput.files.length === 0) {
                        return '';
                    }

                    return fileInput.files[0].name || '';
                };

                var toggleSubmit = function () {
                    submitButton.disabled = !fileInput.files || fileInput.files.length === 0;
                    duplicateConfirmationInput.value = '0';
                };

                var sanitizeFileName = function (fileName) {
                    var normalized = String(fileName);

                    if (typeof normalized.normalize === 'function') {
                        normalized = normalized.normalize('NFD').replace(/[\\u0300-\\u036f]/g, '');
                    }

                    return normalized
                        .replace(/^.*[\\\\\\/]/, '')
                        .replace(/[\\s]+/g, '-')
                        .replace(/[^A-Za-z0-9._-]/g, '');
                };

                var isApprovedFile = function (fileName) {
                    var candidates = [
                        fileName,
                        sanitizeFileName(fileName)
                    ];

                    return candidates.some(function (candidate) {
                        return approvedFileLookup.indexOf(String(candidate).toLowerCase()) !== -1;
                    });
                };

                toggleSubmit();
                fileInput.addEventListener('change', toggleSubmit);

                uploadForm.addEventListener('submit', function (event) {
                    var fileName = selectedFileName();

                    if (duplicateConfirmationInput.value === '1') {
                        return;
                    }

                    if (!fileName || !isApprovedFile(fileName)) {
                        return;
                    }

                    event.preventDefault();
                    requestConfirmation({
                        title: '" . esc_js(__('Archivo ya aprobado', 'cannes-festival-medal-tracker')) . "',
                        message: '" . esc_js(__('Este archivo ya fue confirmado anteriormente. Si continuas, se generara una nueva vista previa y podrias duplicar medallas al aprobarla. Quieres continuar?', 'cannes-festival-medal-tracker')) . "',
                        button: '" . esc_js(__('Continuar', 'cannes-festival-medal-tracker')) . "',
                        variant: 'danger'
                    }).then(function (confirmed) {
                        if (!confirmed) {
                            duplicateConfirmationInput.value = '0';
                            return;
                        }

                        duplicateConfirmationInput.value = '1';

                        if (typeof uploadForm.requestSubmit === 'function') {
                            uploadForm.requestSubmit();
                            return;
                        }

                        uploadForm.submit();
                    });
                });
            });"
        );
    }
}
