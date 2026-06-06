<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportPreviewRenderer
{
    private MedalRepository $repository;

    public function __construct(MedalRepository $repository)
    {
        $this->repository = $repository;
    }

    public function render(array $preview, array $config): void
    {
        if (empty($preview['preview'])) {
            return;
        }

        $imported = is_array($preview['imported'] ?? null) ? $preview['imported'] : [];
        $hasDetectedMedals = $this->hasDetectedMedals($preview);
        $approveConfirmation = $hasDetectedMedals
            ? __('Aprobar y continuar? Esto va a combinar la vista previa con el medallero guardado.', 'cannes-festival-medal-tracker')
            : __('Aprobar y guardar en historial? No se encontraron medallas, asi que el medallero no cambiara.', 'cannes-festival-medal-tracker');
        $approveLabel = $hasDetectedMedals
            ? __('Aprobar y continuar', 'cannes-festival-medal-tracker')
            : __('Aprobar y guardar en historial', 'cannes-festival-medal-tracker');
        ?>
        <div class="<?php echo esc_attr($hasDetectedMedals ? 'fmb-import-preview' : 'fmb-import-preview fmb-import-preview--empty'); ?>">
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
            <?php if (!$hasDetectedMedals) : ?>
                <div class="fmb-zero-results-alert" role="alert">
                    <strong><?php echo esc_html__('No se encontraron resultados para mergear.', 'cannes-festival-medal-tracker'); ?></strong>
                    <p>
                        <?php echo esc_html__('El archivo fue procesado, pero ninguna fila genero medallas para los paises y prizes configurados. Puedes aprobar esta vista previa para guardar la referencia del archivo en el registro historico sin modificar el medallero.', 'cannes-festival-medal-tracker'); ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php $this->renderProcessedRowsAccordion($preview); ?>
            <details class="fmb-accordion" open>
                <summary>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: country count, 2: valid rows. */
                            __('Medallas detectadas: %1$d paises con %2$d filas validas. Ver detalle.', 'cannes-festival-medal-tracker'),
                            count($imported),
                            (int) ($preview['valid_rows'] ?? 0)
                        )
                    );
                    ?>
                </summary>
                <?php $this->renderPreviewMedalsTable($imported); ?>
            </details>
            <div class="fmb-preview-actions">
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    data-fmb-confirm="<?php echo esc_attr($approveConfirmation); ?>"
                    data-fmb-confirm-title="<?php echo esc_attr__('Confirmar aprobacion', 'cannes-festival-medal-tracker'); ?>"
                    data-fmb-confirm-button="<?php echo esc_attr($approveLabel); ?>"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['approve_action']); ?>">
                    <?php wp_nonce_field((string) $config['approve_nonce_action'], (string) $config['approve_nonce_field']); ?>
                    <?php submit_button($approveLabel, 'primary', 'submit', false); ?>
                </form>
                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    data-fmb-confirm="<?php echo esc_attr__('Descartar esta vista previa? No se guardara ningun cambio.', 'cannes-festival-medal-tracker'); ?>"
                    data-fmb-confirm-title="<?php echo esc_attr__('Descartar vista previa', 'cannes-festival-medal-tracker'); ?>"
                    data-fmb-confirm-button="<?php echo esc_attr__('Descartar', 'cannes-festival-medal-tracker'); ?>"
                >
                    <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['discard_action']); ?>">
                    <?php wp_nonce_field((string) $config['discard_nonce_action'], (string) $config['discard_nonce_field']); ?>
                    <?php submit_button(__('Descartar', 'cannes-festival-medal-tracker'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderProcessedRowsAccordion(array $summary): void
    {
        $processedRows = is_array($summary['processed_details'] ?? null) ? $summary['processed_details'] : [];

        if (empty($processedRows)) {
            return;
        }

        ?>
        <details class="fmb-accordion">
            <summary>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: total rows, 2: valid rows, 3: ignored rows. */
                        __('Filas procesadas: %1$d. Validas: %2$d. Ignoradas: %3$d. Ver detalle.', 'cannes-festival-medal-tracker'),
                        (int) ($summary['total_rows'] ?? count($processedRows)),
                        (int) ($summary['valid_rows'] ?? 0),
                        (int) ($summary['ignored_rows'] ?? 0)
                    )
                );
                ?>
            </summary>
            <table class="widefat striped fmb-processed-rows">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Fila', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Estado', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Location', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Prize', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Detalle', 'cannes-festival-medal-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processedRows as $row) : ?>
                        <?php
                        $rowStatus = (string) ($row['status'] ?? '');
                        $status = 'valid' === $rowStatus
                            ? __('Procesada', 'cannes-festival-medal-tracker')
                            : __('Ignorada', 'cannes-festival-medal-tracker');
                        $detail = $this->formatProcessedRowDetail($row);
                        ?>
                        <tr class="<?php echo esc_attr('valid' === $rowStatus ? 'fmb-row-valid' : 'fmb-row-ignored'); ?>">
                            <td><?php echo esc_html((string) absint($row['row'] ?? 0)); ?></td>
                            <td><?php echo esc_html($status); ?></td>
                            <td><?php echo esc_html((string) ($row['raw_location'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['raw_prize'] ?? '')); ?></td>
                            <td><?php echo esc_html($detail); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($summary['ignored_rows'])) : ?>
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
        </details>
        <?php
    }

    private function formatProcessedRowDetail(array $row): string
    {
        $status = (string) ($row['status'] ?? '');
        $countedCountries = is_array($row['countries'] ?? null) ? array_map('strval', $row['countries']) : [];
        $ignoredCountries = is_array($row['ignored_countries'] ?? null) ? array_map('strval', $row['ignored_countries']) : [];
        $parts = [];

        if ('valid' === $status) {
            $parts[] = sprintf(
                /* translators: %s: medal key. */
                __('Medalla: %s.', 'cannes-festival-medal-tracker'),
                (string) ($row['medal'] ?? '')
            );

            if (!empty($countedCountries)) {
                $parts[] = sprintf(
                    /* translators: %s: counted country list. */
                    __('Contabilice: %s.', 'cannes-festival-medal-tracker'),
                    implode(', ', $countedCountries)
                );
            }
        } else {
            $parts[] = (string) ($row['reason'] ?? '');

            if (!empty($countedCountries)) {
                $parts[] = sprintf(
                    /* translators: %s: allowed country list detected in ignored row. */
                    __('Paises permitidos detectados: %s.', 'cannes-festival-medal-tracker'),
                    implode(', ', $countedCountries)
                );
            }
        }

        if (!empty($ignoredCountries)) {
            $parts[] = sprintf(
                /* translators: %s: ignored country list. */
                __('Ignore: %s.', 'cannes-festival-medal-tracker'),
                implode(', ', $ignoredCountries)
            );
        }

        return implode(' ', array_filter($parts));
    }

    private function renderPreviewMedalsTable(array $items): void
    {
        if (empty($items)) {
            ?>
            <p class="fmb-empty-preview">
                <?php echo esc_html__('No hay medallas detectadas para mostrar. Aprobar esta vista previa solo guardara el archivo en el historial de importaciones aprobadas.', 'cannes-festival-medal-tracker'); ?>
            </p>
            <?php
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
                    <th scope="col"><?php echo esc_html__('Accion en base de datos', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
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
        <?php
    }

    private function hasDetectedMedals(array $summary): bool
    {
        return !empty($summary['imported']) && is_array($summary['imported']);
    }
}
