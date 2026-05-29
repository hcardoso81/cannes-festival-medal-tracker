<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Application\ImportMedalsUseCase;
use FestivalMedalTracker\Infrastructure\Logging\FileLogger;
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
    private const APPROVE_ACTION = 'fmb_approve_import_preview';
    private const APPROVE_NONCE_ACTION = 'fmb_approve_import_preview_nonce';
    private const APPROVE_NONCE_FIELD = 'fmb_approve_preview_nonce';
    private const DISCARD_ACTION = 'fmb_discard_import_preview';
    private const DISCARD_NONCE_ACTION = 'fmb_discard_import_preview_nonce';
    private const DISCARD_NONCE_FIELD = 'fmb_discard_preview_nonce';
    private const TRANSIENT_PREFIX = 'fmb_import_summary_';
    private const PREVIEW_TRANSIENT_PREFIX = 'fmb_import_preview_';

    private ImportMedalsUseCase $importer;

    private MedalRepository $repository;

    private FileLogger $logger;

    public function __construct(ImportMedalsUseCase $importer, MedalRepository $repository, FileLogger $logger)
    {
        $this->importer   = $importer;
        $this->repository = $repository;
        $this->logger     = $logger;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'handleImport']);
        add_action('admin_post_' . self::APPROVE_ACTION, [$this, 'handleApprovePreview']);
        add_action('admin_post_' . self::DISCARD_ACTION, [$this, 'handleDiscardPreview']);
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
        if ('toplevel_page_' . self::MENU_SLUG !== $hookSuffix) {
            return;
        }

        wp_enqueue_style(
            'fmb-admin',
            FMB_URL . 'assets/css/admin.css',
            [],
            FMB_VERSION
        );
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

    public function handleReset(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para reiniciar el medallero.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::RESET_NONCE_ACTION, self::RESET_NONCE_FIELD);

        $deleted = $this->repository->deleteAll();
        delete_transient($this->previewTransientKey());

        $this->logger->warning(
            'Medal standings were reset from admin.',
            [
                'user_id'      => get_current_user_id(),
                'deleted_rows' => $deleted,
            ]
        );

        set_transient(
            self::TRANSIENT_PREFIX . get_current_user_id(),
            [
                'summary' => [
                    'reset'        => true,
                    'deleted_rows' => $deleted,
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
        ?>
        <div class="wrap fmb-admin-page">
            <h1><?php echo esc_html__('Medallero del Festival', 'cannes-festival-medal-tracker'); ?></h1>

            <?php $this->renderNotice(is_array($notice) ? $notice : []); ?>

            <?php $this->renderCountingRules(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="fmb-upload-form">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="fmb_medal_file"><?php echo esc_html__('Archivo Excel', 'cannes-festival-medal-tracker'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="fmb_medal_file" name="fmb_medal_file" accept=".xlsx,.xls,.csv" required>
                                <p class="description">
                                    <?php echo esc_html__('Columnas esperadas: location y prize.', 'cannes-festival-medal-tracker'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Generar vista previa', 'cannes-festival-medal-tracker')); ?>
            </form>

            <?php $this->renderPendingPreview(is_array($preview) ? $preview : []); ?>

            <h2><?php echo esc_html__('Vista previa de shortcodes', 'cannes-festival-medal-tracker'); ?></h2>
            <div class="fmb-shortcode-previews">
                <h3><?php echo esc_html('[medalByCountry]'); ?></h3>
                <?php echo do_shortcode('[medalByCountry]'); ?>

                <h3><?php echo esc_html('[medalsTotal]'); ?></h3>
                <?php echo do_shortcode('[medalsTotal]'); ?>

                <h3><?php echo esc_html('[medalByCountryDetail]'); ?></h3>
                <?php echo do_shortcode('[medalByCountryDetail]'); ?>
            </div>

            <h2><?php echo esc_html__('Medallero actual', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderCurrentTable($rows); ?>

            <div class="fmb-danger-zone">
                <h2><?php echo esc_html__('Reiniciar medallero', 'cannes-festival-medal-tracker'); ?></h2>
                <p><?php echo esc_html__('Esto elimina todas las filas de medallas de la tabla del plugin. Esta accion no se puede deshacer.', 'cannes-festival-medal-tracker'); ?></p>
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    onsubmit="return confirm('<?php echo esc_js(__('Estas seguro de que quieres reiniciar el medallero?', 'cannes-festival-medal-tracker')); ?>');"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::RESET_ACTION); ?>">
                    <?php wp_nonce_field(self::RESET_NONCE_ACTION, self::RESET_NONCE_FIELD); ?>
                    <?php submit_button(__('Reiniciar medallero', 'cannes-festival-medal-tracker'), 'delete', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderCountingRules(): void
    {
        $countries = $this->importer->getAllowedCountries();
        $synonyms  = $this->importer->getPrizeSynonyms();
        ?>
        <div class="fmb-counting-rules">
            <h2><?php echo esc_html__('Reglas de contabilizacion', 'cannes-festival-medal-tracker'); ?></h2>
            <div class="fmb-rules-grid">
                <div>
                    <h3><?php echo esc_html__('Paises contabilizados', 'cannes-festival-medal-tracker'); ?></h3>
                    <ul class="fmb-chip-list">
                        <?php foreach ($countries as $country) : ?>
                            <li><?php echo esc_html((string) $country); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3><?php echo esc_html__('Valores de prize contabilizados', 'cannes-festival-medal-tracker'); ?></h3>
                    <table class="widefat striped fmb-rules-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Medalla', 'cannes-festival-medal-tracker'); ?></th>
                                <th scope="col"><?php echo esc_html__('Valores de prize aceptados (el sistema convierte todo a minuscula antes de procesar; no importa si en el Excel vienen en mayusculas o capitalizados)', 'cannes-festival-medal-tracker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (['gp', 'gold', 'silver', 'bronze'] as $medal) : ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($this->medalLabel($medal)); ?></th>
                                    <td><?php echo esc_html(implode(', ', array_map('strval', $synonyms[$medal] ?? []))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
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

    private function renderNotice(array $notice): void
    {
        if (empty($notice)) {
            return;
        }

        if (!empty($notice['error'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html((string) $notice['error']); ?></p>
            </div>
            <?php
            return;
        }

        $summary = is_array($notice['summary'] ?? null) ? $notice['summary'] : [];

        if (empty($summary)) {
            return;
        }

        if (!empty($summary['reset'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: deleted database rows. */
                            __('Reinicio del medallero completado. Filas eliminadas: %d.', 'cannes-festival-medal-tracker'),
                            (int) ($summary['deleted_rows'] ?? 0)
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        if (!empty($summary['discarded'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__('Vista previa descartada. No se guardaron cambios en la base de datos.', 'cannes-festival-medal-tracker'); ?></p>
            </div>
            <?php
            return;
        }

        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                if (!empty($summary['committed'])) {
                    echo esc_html(
                        sprintf(
                            /* translators: 1: valid rows, 2: ignored rows, 3: created countries, 4: updated countries. */
                            __('Importacion aprobada y guardada. Filas validas: %1$d. Filas ignoradas: %2$d. Paises creados: %3$d. Paises actualizados: %4$d.', 'cannes-festival-medal-tracker'),
                            (int) $summary['valid_rows'],
                            (int) $summary['ignored_rows'],
                            (int) ($summary['countries_created'] ?? 0),
                            (int) ($summary['countries_updated'] ?? 0)
                        )
                    );
                } else {
                    echo esc_html(
                        sprintf(
                            /* translators: 1: valid rows, 2: ignored rows. */
                            __('Vista previa de importacion lista. Filas validas: %1$d. Filas ignoradas: %2$d. Revisa la vista previa y luego aprueba para guardar los datos.', 'cannes-festival-medal-tracker'),
                            (int) $summary['valid_rows'],
                            (int) $summary['ignored_rows']
                        )
                    );
                }
                ?>
            </p>
            <?php if (!empty($summary['errors']) && is_array($summary['errors'])) : ?>
                <ul class="fmb-import-errors">
                    <?php foreach ($summary['errors'] as $error) : ?>
                        <li><?php echo esc_html((string) $error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: plugin log path. */
                            __('El detalle completo de filas ignoradas tambien se escribio en %s.', 'cannes-festival-medal-tracker'),
                            'logs/fmb-error.log'
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderPendingPreview(array $preview): void
    {
        if (empty($preview['preview']) || empty($preview['imported']) || !is_array($preview['imported'])) {
            return;
        }

        ?>
        <div class="fmb-import-preview">
            <h2><?php echo esc_html__('Vista previa pendiente de importacion', 'cannes-festival-medal-tracker'); ?></h2>
            <?php if (!empty($preview['source_file'])) : ?>
                <p>
                    <strong><?php echo esc_html__('Archivo procesado:', 'cannes-festival-medal-tracker'); ?></strong>
                    <?php echo esc_html((string) $preview['source_file']); ?>
                </p>
            <?php endif; ?>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: valid rows, 2: ignored rows. */
                        __('Esta vista previa encontro %1$d filas validas y %2$d filas ignoradas. Todavia no se guardo nada.', 'cannes-festival-medal-tracker'),
                        (int) ($preview['valid_rows'] ?? 0),
                        (int) ($preview['ignored_rows'] ?? 0)
                    )
                );
                ?>
            </p>
            <table class="widefat striped fmb-admin-standings">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Accion en base de datos', 'cannes-festival-medal-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['imported'] as $item) : ?>
                        <?php
                        $country = (string) ($item['country'] ?? '');
                        $medals  = is_array($item['medals'] ?? null) ? $item['medals'] : [];
                        $total   = absint($medals['gp'] ?? 0) + absint($medals['gold'] ?? 0) + absint($medals['silver'] ?? 0) + absint($medals['bronze'] ?? 0);
                        $action  = null === $this->repository->findByCountry($country)
                            ? __('Crear', 'cannes-festival-medal-tracker')
                            : __('Actualizar', 'cannes-festival-medal-tracker');
                        ?>
                        <tr>
                            <td><?php echo esc_html($country); ?></td>
                            <td><?php echo esc_html((string) absint($medals['gp'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($medals['gold'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($medals['silver'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($medals['bronze'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) $total); ?></td>
                            <td><?php echo esc_html($action); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="fmb-preview-actions">
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    onsubmit="return confirm('<?php echo esc_js(__('Aprobar y continuar? Esto va a combinar la vista previa con el medallero guardado.', 'cannes-festival-medal-tracker')); ?>');"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::APPROVE_ACTION); ?>">
                    <?php wp_nonce_field(self::APPROVE_NONCE_ACTION, self::APPROVE_NONCE_FIELD); ?>
                    <?php submit_button(__('Aprobar y continuar', 'cannes-festival-medal-tracker'), 'primary', 'submit', false); ?>
                </form>
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    onsubmit="return confirm('<?php echo esc_js(__('Descartar esta vista previa? No se guardara ningun cambio.', 'cannes-festival-medal-tracker')); ?>');"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::DISCARD_ACTION); ?>">
                    <?php wp_nonce_field(self::DISCARD_NONCE_ACTION, self::DISCARD_NONCE_FIELD); ?>
                    <?php submit_button(__('Descartar', 'cannes-festival-medal-tracker'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderCurrentTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html__('Todavia no se importaron medallas.', 'cannes-festival-medal-tracker') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $row['country']); ?></td>
                        <td><?php echo esc_html((string) absint($row['gp'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['gold'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['silver'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['bronze'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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

    private function medalLabel(string $medal): string
    {
        $labels = [
            'gp'     => __('GP', 'cannes-festival-medal-tracker'),
            'gold'   => __('Oro', 'cannes-festival-medal-tracker'),
            'silver' => __('Plata', 'cannes-festival-medal-tracker'),
            'bronze' => __('Bronce', 'cannes-festival-medal-tracker'),
        ];

        return (string) ($labels[$medal] ?? $medal);
    }
}
