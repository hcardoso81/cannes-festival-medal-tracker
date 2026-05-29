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
        add_action('admin_post_' . self::RESET_ACTION, [$this, 'handleReset']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Festival Medal Tracker', 'cannes-festival-medal-tracker'),
            __('Medal Tracker', 'cannes-festival-medal-tracker'),
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
            wp_die(esc_html__('You are not allowed to import medals.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $summary = null;
        $error   = '';

        $filePath = '';

        try {
            $filePath = $this->handleUpload();
            $summary  = $this->importer->preview($filePath);
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
            $error = __('The import could not be completed. Please verify the file format and try again.', 'cannes-festival-medal-tracker');
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

    public function handleApprovePreview(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to approve imports.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::APPROVE_NONCE_ACTION, self::APPROVE_NONCE_FIELD);

        $preview = get_transient($this->previewTransientKey());
        $summary = null;
        $error   = '';

        if (!is_array($preview) || empty($preview['preview'])) {
            $error = __('There is no pending import preview to approve.', 'cannes-festival-medal-tracker');
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
                $error = __('The approved import could not be persisted. Please review the log.', 'cannes-festival-medal-tracker');
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
            wp_die(esc_html__('You are not allowed to reset medals.', 'cannes-festival-medal-tracker'));
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
            wp_die(esc_html__('You are not allowed to access this page.', 'cannes-festival-medal-tracker'));
        }

        $notice = get_transient(self::TRANSIENT_PREFIX . get_current_user_id());
        delete_transient(self::TRANSIENT_PREFIX . get_current_user_id());
        $preview = get_transient($this->previewTransientKey());
        $rows = $this->repository->getCountryDetails();
        ?>
        <div class="wrap fmb-admin-page">
            <h1><?php echo esc_html__('Festival Medal Tracker', 'cannes-festival-medal-tracker'); ?></h1>

            <?php $this->renderNotice(is_array($notice) ? $notice : []); ?>

            <?php $this->renderCountingRules(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="fmb-upload-form">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="fmb_medal_file"><?php echo esc_html__('Excel file', 'cannes-festival-medal-tracker'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="fmb_medal_file" name="fmb_medal_file" accept=".xlsx,.xls,.csv" required>
                                <p class="description">
                                    <?php echo esc_html__('Expected columns: location and prize.', 'cannes-festival-medal-tracker'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Import medals', 'cannes-festival-medal-tracker')); ?>
            </form>

            <?php $this->renderPendingPreview(is_array($preview) ? $preview : []); ?>

            <h2><?php echo esc_html__('Shortcode previews', 'cannes-festival-medal-tracker'); ?></h2>
            <div class="fmb-shortcode-previews">
                <h3><?php echo esc_html('[medalByCountry]'); ?></h3>
                <?php echo do_shortcode('[medalByCountry]'); ?>

                <h3><?php echo esc_html('[medalsTotal]'); ?></h3>
                <?php echo do_shortcode('[medalsTotal]'); ?>

                <h3><?php echo esc_html('[medalByCountryDetail]'); ?></h3>
                <?php echo do_shortcode('[medalByCountryDetail]'); ?>
            </div>

            <h2><?php echo esc_html__('Current standings', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderCurrentTable($rows); ?>

            <div class="fmb-danger-zone">
                <h2><?php echo esc_html__('Reset medal standings', 'cannes-festival-medal-tracker'); ?></h2>
                <p><?php echo esc_html__('This removes all medal rows from the plugin table. This action cannot be undone.', 'cannes-festival-medal-tracker'); ?></p>
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset the medal standings?', 'cannes-festival-medal-tracker')); ?>');"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::RESET_ACTION); ?>">
                    <?php wp_nonce_field(self::RESET_NONCE_ACTION, self::RESET_NONCE_FIELD); ?>
                    <?php submit_button(__('Reset medal standings', 'cannes-festival-medal-tracker'), 'delete', 'submit', false); ?>
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
            <h2><?php echo esc_html__('Counting rules', 'cannes-festival-medal-tracker'); ?></h2>
            <div class="fmb-rules-grid">
                <div>
                    <h3><?php echo esc_html__('Countries counted', 'cannes-festival-medal-tracker'); ?></h3>
                    <ul class="fmb-chip-list">
                        <?php foreach ($countries as $country) : ?>
                            <li><?php echo esc_html((string) $country); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3><?php echo esc_html__('Prize values counted', 'cannes-festival-medal-tracker'); ?></h3>
                    <table class="widefat striped fmb-rules-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Medal', 'cannes-festival-medal-tracker'); ?></th>
                                <th scope="col"><?php echo esc_html__('Accepted prize values', 'cannes-festival-medal-tracker'); ?></th>
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

    private function handleUpload(): string
    {
        if (empty($_FILES['fmb_medal_file']) || !is_array($_FILES['fmb_medal_file'])) {
            throw new RuntimeException(__('No file was uploaded.', 'cannes-festival-medal-tracker'));
        }

        $file = $_FILES['fmb_medal_file'];

        if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
            throw new RuntimeException(__('The file upload failed.', 'cannes-festival-medal-tracker'));
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
            throw new RuntimeException(__('Only XLSX, XLS or CSV files are allowed.', 'cannes-festival-medal-tracker'));
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

        return (string) $uploaded['file'];
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
                            __('Medal standings reset complete. Deleted rows: %d.', 'cannes-festival-medal-tracker'),
                            (int) ($summary['deleted_rows'] ?? 0)
                        )
                    );
                    ?>
                </p>
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
                            __('Import approved and persisted. Valid rows: %1$d. Ignored rows: %2$d. Countries created: %3$d. Countries updated: %4$d.', 'cannes-festival-medal-tracker'),
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
                            __('Import preview ready. Valid rows: %1$d. Ignored rows: %2$d. Review the preview below, then approve to persist the data.', 'cannes-festival-medal-tracker'),
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
                            __('Full ignored-row details were also written to %s.', 'cannes-festival-medal-tracker'),
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
            <h2><?php echo esc_html__('Pending import preview', 'cannes-festival-medal-tracker'); ?></h2>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: valid rows, 2: ignored rows. */
                        __('This preview found %1$d valid rows and %2$d ignored rows. Nothing has been saved yet.', 'cannes-festival-medal-tracker'),
                        (int) ($preview['valid_rows'] ?? 0),
                        (int) ($preview['ignored_rows'] ?? 0)
                    )
                );
                ?>
            </p>
            <table class="widefat striped fmb-admin-standings">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Country', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Gold', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Silver', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Bronze', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Database action', 'cannes-festival-medal-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['imported'] as $item) : ?>
                        <?php
                        $country = (string) ($item['country'] ?? '');
                        $medals  = is_array($item['medals'] ?? null) ? $item['medals'] : [];
                        $total   = absint($medals['gp'] ?? 0) + absint($medals['gold'] ?? 0) + absint($medals['silver'] ?? 0) + absint($medals['bronze'] ?? 0);
                        $action  = null === $this->repository->findByCountry($country)
                            ? __('Create', 'cannes-festival-medal-tracker')
                            : __('Update', 'cannes-festival-medal-tracker');
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
            <form
                class="fmb-approve-preview-form"
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                onsubmit="return confirm('<?php echo esc_js(__('Approve and continue? This will merge the preview with the persisted medal standings.', 'cannes-festival-medal-tracker')); ?>');"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr(self::APPROVE_ACTION); ?>">
                <?php wp_nonce_field(self::APPROVE_NONCE_ACTION, self::APPROVE_NONCE_FIELD); ?>
                <?php submit_button(__('Approve and continue', 'cannes-festival-medal-tracker'), 'primary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private function renderCurrentTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html__('No medals have been imported yet.', 'cannes-festival-medal-tracker') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Country', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Gold', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Silver', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronze', 'cannes-festival-medal-tracker'); ?></th>
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
            'gold'   => __('Gold', 'cannes-festival-medal-tracker'),
            'silver' => __('Silver', 'cannes-festival-medal-tracker'),
            'bronze' => __('Bronze', 'cannes-festival-medal-tracker'),
        ];

        return (string) ($labels[$medal] ?? $medal);
    }
}
