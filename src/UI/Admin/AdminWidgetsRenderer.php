<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Application\ImportMedalsUseCase;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminWidgetsRenderer
{
    private ImportMedalsUseCase $importer;

    private AdminShortcodePreviewRenderer $shortcodes;

    public function __construct(ImportMedalsUseCase $importer)
    {
        $this->importer = $importer;
        $this->shortcodes = new AdminShortcodePreviewRenderer();
    }

    public function renderUploadForm(array $config): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="fmb-upload-form">
            <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['action']); ?>">
            <input type="hidden" id="fmb_duplicate_import_confirmed" name="fmb_duplicate_import_confirmed" value="0">
            <?php wp_nonce_field((string) $config['nonce_action'], (string) $config['nonce_field']); ?>

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

            <?php
            submit_button(
                __('Generar vista previa', 'cannes-festival-medal-tracker'),
                'primary',
                'submit',
                true,
                [
                    'id'       => 'fmb_generate_preview',
                    'disabled' => 'disabled',
                ]
            );
            ?>
        </form>
        <?php
    }

    public function renderShortcodePreviews(array $rows): void
    {
        ?>
        <h2><?php echo esc_html__('Shortcodes actuales', 'cannes-festival-medal-tracker'); ?></h2>
        <?php $this->shortcodes->render($rows); ?>
        <?php
    }

    public function renderResetZone(array $config): void
    {
        $confirmationPhrase = (string) $config['reset_confirmation_phrase'];
        ?>
        <div class="fmb-danger-zone">
            <h2><?php echo esc_html__('Reiniciar medallero', 'cannes-festival-medal-tracker'); ?></h2>
            <p><?php echo esc_html__('Esto elimina todas las filas de medallas de la tabla del plugin. Esta accion no se puede deshacer.', 'cannes-festival-medal-tracker'); ?></p>
            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                data-fmb-confirm="<?php echo esc_attr__('Estas seguro de que quieres reiniciar el medallero?', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-title="<?php echo esc_attr__('Reiniciar medallero', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-button="<?php echo esc_attr__('Reiniciar medallero', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-variant="danger"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['reset_action']); ?>">
                <?php wp_nonce_field((string) $config['reset_nonce_action'], (string) $config['reset_nonce_field']); ?>
                <p>
                    <label for="fmb_reset_confirmation">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: confirmation phrase required to reset medals. */
                                __('Para confirmar, escribe exactamente: %s', 'cannes-festival-medal-tracker'),
                                $confirmationPhrase
                            )
                        );
                        ?>
                    </label>
                    <input
                        type="text"
                        id="fmb_reset_confirmation"
                        name="fmb_reset_confirmation"
                        class="regular-text"
                        autocomplete="off"
                        required
                        pattern="<?php echo esc_attr($confirmationPhrase); ?>"
                    >
                </p>
                <?php submit_button(__('Reiniciar medallero', 'cannes-festival-medal-tracker'), 'delete', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public function renderCountingRules(): void
    {
        $countries = $this->importer->getCountedCountries();
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

    public function renderCurrentTable(array $rows): void
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
