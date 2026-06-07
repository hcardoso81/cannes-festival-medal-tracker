<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Application\ImportMedalsUseCase;
use FestivalMedalTracker\Infrastructure\Logging\FileLogger;
use FestivalMedalTracker\Infrastructure\Persistence\FrontendPublicationRepository;
use FestivalMedalTracker\Infrastructure\Persistence\ImportRepository;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;
use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPage
{
    private const MENU_SLUG = 'fmb-medal-tracker';
    private const ACTION = 'fmb_import_medals';
    private const NONCE_ACTION = 'fmb_import_medals_nonce';
    private const NONCE_FIELD = 'fmb_import_nonce';
    private const RESET_ACTION = 'fmb_reset_medals';
    private const RESET_NONCE_ACTION = 'fmb_reset_medals_nonce';
    private const RESET_NONCE_FIELD = 'fmb_reset_nonce';
    private const RESET_CONFIRMATION_PHRASE = 'reiniciar medallero';
    private const APPROVE_ACTION = 'fmb_approve_import_preview';
    private const APPROVE_NONCE_ACTION = 'fmb_approve_import_preview_nonce';
    private const APPROVE_NONCE_FIELD = 'fmb_approve_preview_nonce';
    private const DISCARD_ACTION = 'fmb_discard_import_preview';
    private const DISCARD_NONCE_ACTION = 'fmb_discard_import_preview_nonce';
    private const DISCARD_NONCE_FIELD = 'fmb_discard_preview_nonce';
    private const UNDO_ACTION = 'fmb_undo_import';
    private const UNDO_NONCE_ACTION = 'fmb_undo_import_nonce';
    private const UNDO_NONCE_FIELD = 'fmb_undo_import_nonce';
    private const TRANSIENT_PREFIX = 'fmb_import_summary_';
    private const PREVIEW_TRANSIENT_PREFIX = 'fmb_import_preview_';
    private const IMPORT_LOG_LIMIT = 100;

    private ImportMedalsUseCase $importer;

    private MedalRepository $repository;

    private ImportRepository $imports;

    private FrontendPublicationRepository $publication;

    private FileLogger $logger;

    private AdminPageRenderer $renderer;

    private AdminImportHistory $history;

    public function __construct(
        ImportMedalsUseCase $importer,
        MedalRepository $repository,
        ImportRepository $imports,
        FrontendPublicationRepository $publication,
        FileLogger $logger
    ) {
        $this->importer   = $importer;
        $this->repository = $repository;
        $this->imports    = $imports;
        $this->publication = $publication;
        $this->logger     = $logger;
        $this->renderer   = new AdminPageRenderer($importer, $repository);
        $this->history    = new AdminImportHistory($imports);
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'handleImport']);
        add_action('admin_post_' . self::APPROVE_ACTION, [$this, 'handleApprovePreview']);
        add_action('admin_post_' . self::DISCARD_ACTION, [$this, 'handleDiscardPreview']);
        add_action('admin_post_' . self::UNDO_ACTION, [$this, 'handleUndoImport']);
        add_action('admin_post_' . self::RESET_ACTION, [$this, 'handleReset']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Medallero del Festival', 'cannes-festival-medal-tracker'),
            __('Medallero', 'cannes-festival-medal-tracker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage'],
            'dashicons-awards',
            58
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        AdminAssets::enqueue($hookSuffix, 'toplevel_page_' . self::MENU_SLUG, $this->history->approvedSourceFiles());
    }

    public function handleImport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para importar medallas.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $summary = null;
        $error   = '';

        $filePath = '';
        $fileName = '';

        try {
            $this->validateDuplicateImportConfirmation();
            $upload   = $this->handleUpload();
            $filePath = $upload['path'];
            $fileName = $upload['name'];
            $summary  = $this->importer->preview($filePath);
            $summary['source_file'] = $fileName;
            $this->logIgnoredRows($summary);
            set_transient($this->previewTransientKey(), $summary, HOUR_IN_SECONDS);
        } catch (RuntimeException $runtimeException) {
            $error = $runtimeException->getMessage();
            $this->logger->error(
                'Import runtime error.',
                [
                    'user_id' => get_current_user_id(),
                    'error'   => $runtimeException->getMessage(),
                ]
            );
        } catch (Throwable $throwable) {
            $this->logger->exception(
                $throwable,
                [
                    'user_id' => get_current_user_id(),
                ]
            );
            $error = __('No se pudo completar la importacion. Verifica el formato del archivo e intenta nuevamente.', 'cannes-festival-medal-tracker');
        } finally {
            if ('' !== $filePath && file_exists($filePath)) {
                wp_delete_file($filePath);
            }
        }

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => $summary,
                'error'   => $error,
            ],
            MINUTE_IN_SECONDS * 10
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    public function handleDiscardPreview(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para descartar importaciones.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::DISCARD_NONCE_ACTION, self::DISCARD_NONCE_FIELD);

        $preview = get_transient($this->previewTransientKey());
        delete_transient($this->previewTransientKey());

        $this->logger->warning(
            'Import preview discarded.',
            [
                'user_id'     => get_current_user_id(),
                'source_file' => is_array($preview) ? (string) ($preview['source_file'] ?? '') : '',
                'valid_rows'  => is_array($preview) ? (int) ($preview['valid_rows'] ?? 0) : 0,
            ]
        );

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => [
                    'discarded' => true,
                ],
                'error'   => '',
            ],
            MINUTE_IN_SECONDS * 10
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    public function handleApprovePreview(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para aprobar importaciones.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::APPROVE_NONCE_ACTION, self::APPROVE_NONCE_FIELD);

        $preview = get_transient($this->previewTransientKey());
        $summary = null;
        $error   = '';

        if (!is_array($preview) || empty($preview['preview'])) {
            $error = __('No hay una vista previa de importacion pendiente para aprobar.', 'cannes-festival-medal-tracker');
        } else {
            try {
                $summary = $this->importer->commitPreview($preview);
                delete_transient($this->previewTransientKey());

                $this->logger->warning(
                    'Import preview approved and persisted.',
                    [
                        'user_id'           => get_current_user_id(),
                        'source_file'       => (string) ($summary['source_file'] ?? ''),
                        'valid_rows'        => (int) ($summary['valid_rows'] ?? 0),
                        'ignored_rows'      => (int) ($summary['ignored_rows'] ?? 0),
                        'countries_created' => (int) ($summary['countries_created'] ?? 0),
                        'countries_updated' => (int) ($summary['countries_updated'] ?? 0),
                    ]
                );
            } catch (Throwable $throwable) {
                $this->logger->exception(
                    $throwable,
                    [
                        'user_id' => get_current_user_id(),
                        'action'  => self::APPROVE_ACTION,
                    ]
                );
                $error = __('No se pudo guardar la importacion aprobada. Revisa el log.', 'cannes-festival-medal-tracker');
            }
        }

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => $summary,
                'error'   => $error,
            ],
            MINUTE_IN_SECONDS * 10
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    public function handleUndoImport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para deshacer importaciones.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::UNDO_NONCE_ACTION, self::UNDO_NONCE_FIELD);

        $importId = isset($_POST['fmb_import_id'])
            ? absint($_POST['fmb_import_id'])
            : 0;
        $summary = null;
        $error   = '';

        try {
            $summary = $this->importer->undoImport($importId);

            $this->logger->warning(
                'Approved import was undone from admin.',
                [
                    'user_id'       => get_current_user_id(),
                    'import_id'     => $importId,
                    'source_file'   => (string) ($summary['source_file'] ?? ''),
                    'undone_rows'   => (int) ($summary['undone_rows'] ?? 0),
                    'undone_medals' => (int) ($summary['undone_medals'] ?? 0),
                ]
            );
        } catch (Throwable $throwable) {
            $this->logger->exception(
                $throwable,
                [
                    'user_id'   => get_current_user_id(),
                    'action'    => self::UNDO_ACTION,
                    'import_id' => $importId,
                ]
            );
            $error = $throwable instanceof RuntimeException
                ? $throwable->getMessage()
                : __('No se pudo deshacer la importacion. Revisa el log.', 'cannes-festival-medal-tracker');
        }

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => is_array($summary)
                    ? [
                        'undone'        => true,
                        'source_file'   => (string) ($summary['source_file'] ?? ''),
                        'undone_rows'   => (int) ($summary['undone_rows'] ?? 0),
                        'undone_medals' => (int) ($summary['undone_medals'] ?? 0),
                    ]
                    : [],
                'error'   => $error,
            ],
            MINUTE_IN_SECONDS * 10
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    public function handleReset(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para reiniciar el medallero.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::RESET_NONCE_ACTION, self::RESET_NONCE_FIELD);

        $confirmation = isset($_POST['fmb_reset_confirmation'])
            ? sanitize_text_field(wp_unslash($_POST['fmb_reset_confirmation']))
            : '';

        if ($confirmation !== self::RESET_CONFIRMATION_PHRASE) {
            set_transient(
                self::TRANSIENT_PREFIX . get_current_user_id(),
                [
                    'summary' => [],
                    'error'   => __('No se reinicio el medallero. Escribe exactamente "reiniciar medallero" para confirmar el borrado.', 'cannes-festival-medal-tracker'),
                ],
                MINUTE_IN_SECONDS * 10
            );

            wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
            exit;
        }

        $deleted = $this->repository->deleteAll();
        $deletedImportLogEntries = count($this->getImportLog());
        $deletedImportRows = $this->imports->deleteAll();
        $deletedPublishedRows = $this->publication->clearPublishedData();
        delete_transient($this->previewTransientKey());

        $this->logger->warning(
            'Medal standings were reset from admin.',
            [
                'user_id'                    => get_current_user_id(),
                'deleted_rows'               => $deleted,
                'deleted_import_log_entries' => $deletedImportLogEntries,
                'deleted_import_rows'        => $deletedImportRows,
                'deleted_published_rows'     => $deletedPublishedRows,
            ]
        );

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => [
                    'reset'                      => true,
                    'deleted_rows'               => $deleted,
                    'deleted_import_log_entries' => $deletedImportLogEntries,
                    'deleted_import_rows'        => $deletedImportRows,
                    'deleted_published_rows'     => $deletedPublishedRows,
                ],
                'error'   => '',
            ],
            MINUTE_IN_SECONDS * 10
        );

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'cannes-festival-medal-tracker'));
        }

        $notice = get_transient(self::TRANSIENT_PREFIX . get_current_user_id());
        delete_transient(self::TRANSIENT_PREFIX . get_current_user_id());
        $preview = get_transient($this->previewTransientKey());
        $rows = $this->repository->getCountryDetails();
        $importLog = $this->getImportLog();

        $this->renderer->renderPage(
            is_array($notice) ? $notice : [],
            is_array($preview) ? $preview : [],
            $rows,
            $importLog,
            $this->viewConfig()
        );
    }

    private function viewConfig(): array
    {
        return [
            'action'                    => self::ACTION,
            'nonce_action'              => self::NONCE_ACTION,
            'nonce_field'               => self::NONCE_FIELD,
            'approve_action'            => self::APPROVE_ACTION,
            'approve_nonce_action'      => self::APPROVE_NONCE_ACTION,
            'approve_nonce_field'       => self::APPROVE_NONCE_FIELD,
            'discard_action'            => self::DISCARD_ACTION,
            'discard_nonce_action'      => self::DISCARD_NONCE_ACTION,
            'discard_nonce_field'       => self::DISCARD_NONCE_FIELD,
            'undo_action'               => self::UNDO_ACTION,
            'undo_nonce_action'         => self::UNDO_NONCE_ACTION,
            'undo_nonce_field'          => self::UNDO_NONCE_FIELD,
            'reset_action'              => self::RESET_ACTION,
            'reset_nonce_action'        => self::RESET_NONCE_ACTION,
            'reset_nonce_field'         => self::RESET_NONCE_FIELD,
            'reset_confirmation_phrase' => self::RESET_CONFIRMATION_PHRASE,
        ];
    }

    private function handleUpload(): array
    {
        if (empty($_FILES['fmb_medal_file']) || !is_array($_FILES['fmb_medal_file'])) {
            throw new RuntimeException(__('No se subio ningun archivo.', 'cannes-festival-medal-tracker'));
        }

        $file = $_FILES['fmb_medal_file'];

        if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
            throw new RuntimeException(__('La carga del archivo fallo.', 'cannes-festival-medal-tracker'));
        }

        $allowedMimes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'csv'  => 'text/csv',
        ];

        $fileType = wp_check_filetype_and_ext(
            (string) $file['tmp_name'],
            sanitize_file_name((string) $file['name']),
            $allowedMimes
        );

        if (empty($fileType['ext']) || !isset($allowedMimes[$fileType['ext']])) {
            throw new RuntimeException(__('Solo se permiten archivos XLSX, XLS o CSV.', 'cannes-festival-medal-tracker'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload(
            $file,
            [
                'test_form' => false,
                'mimes'     => $allowedMimes,
            ]
        );

        if (!empty($uploaded['error'])) {
            throw new RuntimeException(sanitize_text_field((string) $uploaded['error']));
        }

        return [
            'path' => (string) $uploaded['file'],
            'name' => sanitize_file_name((string) $file['name']),
        ];
    }

    private function validateDuplicateImportConfirmation(): void
    {
        if (empty($_FILES['fmb_medal_file']) || !is_array($_FILES['fmb_medal_file'])) {
            return;
        }

        $file = $_FILES['fmb_medal_file'];
        $fileName = sanitize_file_name((string) ($file['name'] ?? ''));

        if ('' === $fileName || !$this->hasApprovedImportForFile($fileName)) {
            return;
        }

        $confirmed = isset($_POST['fmb_duplicate_import_confirmed'])
            ? sanitize_text_field(wp_unslash($_POST['fmb_duplicate_import_confirmed']))
            : '0';

        if ('1' === $confirmed) {
            return;
        }

        $this->logger->warning(
            'Duplicate confirmed import upload blocked before preview.',
            [
                'user_id'     => get_current_user_id(),
                'source_file' => $fileName,
            ]
        );

        throw new RuntimeException(__('Este archivo ya fue confirmado anteriormente. Vuelve a seleccionarlo y confirma que quieres continuar.', 'cannes-festival-medal-tracker'));
    }

    private function getImportLog(): array
    {
        return $this->history->list(self::IMPORT_LOG_LIMIT);
    }

    private function hasApprovedImportForFile(string $fileName): bool
    {
        return $this->history->hasApprovedFile($fileName);
    }

    private function logIgnoredRows(?array $summary): void
    {
        if (empty($summary['ignored_details']) || !is_array($summary['ignored_details'])) {
            return;
        }

        $this->logger->warning(
            'Import completed with ignored rows.',
            [
                'user_id'      => get_current_user_id(),
                'total_rows'   => (int) ($summary['total_rows'] ?? 0),
                'valid_rows'   => (int) ($summary['valid_rows'] ?? 0),
                'ignored_rows' => (int) ($summary['ignored_rows'] ?? 0),
                'rows'         => $summary['ignored_details'],
            ]
        );
    }

    private function previewTransientKey(): string
    {
        return self::PREVIEW_TRANSIENT_PREFIX . get_current_user_id();
    }

}
