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

        wp_enqueue_style(
            'fmb-admin',
            FMB_URL . 'assets/css/admin.css',
            [],
            FMB_VERSION
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

                    if (!fileName || !isApprovedFile(fileName)) {
                        return;
                    }

                    var confirmed = confirm('" . esc_js(__('Este archivo ya fue confirmado anteriormente. Si continuas, se generara una nueva vista previa y podrias duplicar medallas al aprobarla. Quieres continuar?', 'cannes-festival-medal-tracker')) . "');

                    if (!confirmed) {
                        event.preventDefault();
                        duplicateConfirmationInput.value = '0';
                        return;
                    }

                    duplicateConfirmationInput.value = '1';
                });
            });"
        );
    }
}
