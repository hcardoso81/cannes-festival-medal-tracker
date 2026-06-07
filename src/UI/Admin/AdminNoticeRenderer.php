<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminNoticeRenderer
{
    public function render(array $notice): void
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
            $this->renderResetNotice($summary);
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

        if (!empty($summary['undone'])) {
            $this->renderUndoNotice($summary);
            return;
        }

        $hasDetectedMedals = $this->hasDetectedMedals($summary);
        $noticeClass = !$hasDetectedMedals && empty($summary['committed'])
            ? 'notice notice-error is-dismissible fmb-zero-results-notice'
            : 'notice notice-success is-dismissible';
        ?>
        <div class="<?php echo esc_attr($noticeClass); ?>">
            <p>
                <?php $this->renderImportNoticeMessage($summary, $hasDetectedMedals); ?>
            </p>
            <?php if (!empty($summary['source_file'])) : ?>
                <p>
                    <strong><?php echo esc_html__('Archivo:', 'cannes-festival-medal-tracker'); ?></strong>
                    <?php echo esc_html((string) $summary['source_file']); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderResetNotice(array $summary): void
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: deleted database rows, 2: deleted approved import log entries, 3: deleted published frontend rows. */
                        __('Reinicio del medallero completado. Filas eliminadas: %1$d. Registros de importaciones aprobadas eliminados: %2$d. Filas publicadas del frontend limpiadas: %3$d.', 'cannes-festival-medal-tracker'),
                        (int) ($summary['deleted_rows'] ?? 0),
                        (int) ($summary['deleted_import_log_entries'] ?? 0),
                        (int) ($summary['deleted_published_rows'] ?? 0)
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    private function renderUndoNotice(array $summary): void
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: source file, 2: country rows, 3: medals removed. */
                        __('Importacion deshecha: %1$s. Paises afectados: %2$d. Medallas removidas: %3$d.', 'cannes-festival-medal-tracker'),
                        (string) ($summary['source_file'] ?? ''),
                        (int) ($summary['undone_rows'] ?? 0),
                        (int) ($summary['undone_medals'] ?? 0)
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    private function renderImportNoticeMessage(array $summary, bool $hasDetectedMedals): void
    {
        if (!empty($summary['committed'])) {
            $message = $hasDetectedMedals
                ? sprintf(
                    /* translators: 1: valid rows, 2: ignored rows, 3: created countries, 4: updated countries. */
                    __('Importacion aprobada y guardada. Filas validas: %1$d. Filas ignoradas: %2$d. Paises creados: %3$d. Paises actualizados: %4$d.', 'cannes-festival-medal-tracker'),
                    (int) $summary['valid_rows'],
                    (int) $summary['ignored_rows'],
                    (int) ($summary['countries_created'] ?? 0),
                    (int) ($summary['countries_updated'] ?? 0)
                )
                : sprintf(
                    /* translators: 1: ignored rows. */
                    __('Importacion aprobada y guardada en el historial. No se encontraron medallas para mergear; filas ignoradas: %1$d.', 'cannes-festival-medal-tracker'),
                    (int) $summary['ignored_rows']
                );

            echo esc_html($message);
            return;
        }

        $message = $hasDetectedMedals
            ? sprintf(
                /* translators: 1: valid rows, 2: ignored rows. */
                __('Vista previa de importacion lista. Filas validas: %1$d. Filas ignoradas: %2$d. Revisa la vista previa y luego aprueba para guardar los datos.', 'cannes-festival-medal-tracker'),
                (int) $summary['valid_rows'],
                (int) $summary['ignored_rows']
            )
            : sprintf(
                /* translators: 1: ignored rows. */
                __('Archivo procesado sin resultados para mergear. Filas ignoradas: %1$d. Puedes aprobar igual para que el archivo quede registrado en el historial.', 'cannes-festival-medal-tracker'),
                (int) $summary['ignored_rows']
            );

        echo esc_html($message);
    }

    private function hasDetectedMedals(array $summary): bool
    {
        return !empty($summary['imported']) && is_array($summary['imported']);
    }
}
