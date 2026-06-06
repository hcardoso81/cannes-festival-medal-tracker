<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportLogRenderer
{
    public function render(array $entries, array $pendingPreview, array $config): void
    {
        ?>
        <h2><?php echo esc_html__('Registro de importaciones', 'cannes-festival-medal-tracker'); ?></h2>
        <?php $this->renderPendingPreviewSummary($pendingPreview); ?>
        <?php if (empty($entries)) : ?>
            <p><?php echo esc_html__('Todavia no hay importaciones aprobadas.', 'cannes-festival-medal-tracker'); ?></p>
            <?php
            return;
        endif;
        ?>
        <details class="fmb-accordion">
            <summary>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: processed import files. */
                        __('Importaciones aprobadas: %d. Ver detalle.', 'cannes-festival-medal-tracker'),
                        count($entries)
                    )
                );
                ?>
            </summary>
            <table class="widefat striped fmb-import-log">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Fecha', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Archivo importado', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Filas validas', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Filas ignoradas', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Paises creados', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Paises actualizados', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Medallas aplicadas', 'cannes-festival-medal-tracker'); ?></th>
                        <th scope="col"><?php echo esc_html__('Acciones', 'cannes-festival-medal-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) : ?>
                        <?php
                        $status = (string) ($entry['status'] ?? 'approved');
                        $canUndo = 'approved' === $status
                            && !empty($entry['id'])
                            && !empty($entry['delta_count']);
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) ($entry['imported_at'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($entry['source_file'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) absint($entry['valid_rows'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($entry['ignored_rows'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($entry['countries_created'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($entry['countries_updated'] ?? 0)); ?></td>
                            <td><?php echo esc_html((string) absint($entry['medal_delta_total'] ?? 0)); ?></td>
                            <td><?php $this->renderUndoAction($entry, $canUndo, $config); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <?php
    }

    private function renderPendingPreviewSummary(array $pendingPreview): void
    {
        if (empty($pendingPreview['preview'])) {
            return;
        }

        ?>
        <div class="<?php echo esc_attr($this->hasDetectedMedals($pendingPreview) ? 'fmb-pending-import' : 'fmb-pending-import fmb-pending-import--empty'); ?>">
            <strong><?php echo esc_html__('Pendiente de aprobar:', 'cannes-festival-medal-tracker'); ?></strong>
            <?php echo esc_html((string) ($pendingPreview['source_file'] ?? __('Archivo sin nombre', 'cannes-festival-medal-tracker'))); ?>
            <span class="fmb-pending-import__status">
                <?php echo esc_html__('No mergeado. Todavia no se guardo en el medallero.', 'cannes-festival-medal-tracker'); ?>
            </span>
            <span>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: valid rows, 2: ignored rows. */
                        __('Filas validas: %1$d. Filas ignoradas: %2$d.', 'cannes-festival-medal-tracker'),
                        (int) ($pendingPreview['valid_rows'] ?? 0),
                        (int) ($pendingPreview['ignored_rows'] ?? 0)
                    )
                );
                ?>
            </span>
            <?php if (!$this->hasDetectedMedals($pendingPreview)) : ?>
                <span class="fmb-pending-import__warning">
                    <?php echo esc_html__('Sin resultados para mergear. Apruebalo si quieres conservar este archivo en el historial de procesados.', 'cannes-festival-medal-tracker'); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderUndoAction(array $entry, bool $canUndo, array $config): void
    {
        if (!$canUndo) {
            echo esc_html__('Sin medallas para deshacer.', 'cannes-festival-medal-tracker');
            return;
        }

        ?>
        <form
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            data-fmb-confirm="<?php echo esc_attr__('Deshacer esta importacion? Se restaran del medallero las medallas aportadas por este archivo.', 'cannes-festival-medal-tracker'); ?>"
            data-fmb-confirm-title="<?php echo esc_attr__('Deshacer importacion', 'cannes-festival-medal-tracker'); ?>"
            data-fmb-confirm-button="<?php echo esc_attr__('Deshacer', 'cannes-festival-medal-tracker'); ?>"
            data-fmb-confirm-variant="danger"
        >
            <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['undo_action']); ?>">
            <input type="hidden" name="fmb_import_id" value="<?php echo esc_attr((string) absint($entry['id'] ?? 0)); ?>">
            <?php wp_nonce_field((string) $config['undo_nonce_action'], (string) $config['undo_nonce_field']); ?>
            <?php submit_button(__('Deshacer', 'cannes-festival-medal-tracker'), 'delete small', 'submit', false); ?>
        </form>
        <?php
    }

    private function hasDetectedMedals(array $summary): bool
    {
        return !empty($summary['imported']) && is_array($summary['imported']);
    }
}
