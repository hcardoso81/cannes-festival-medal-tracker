<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Infrastructure\Logging\FileLogger;
use FestivalMedalTracker\Infrastructure\Persistence\FrontendPublicationRepository;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendAdminPage
{
    private const PARENT_SLUG = 'fmb-medal-tracker';
    private const MENU_SLUG = 'fmb-medal-tracker-frontend';
    private const VISIBILITY_ACTION = 'fmb_save_frontend_visibility';
    private const VISIBILITY_NONCE_ACTION = 'fmb_save_frontend_visibility_nonce';
    private const VISIBILITY_NONCE_FIELD = 'fmb_frontend_visibility_nonce';
    private const PUBLISH_ACTION = 'fmb_publish_frontend_snapshot';
    private const PUBLISH_NONCE_ACTION = 'fmb_publish_frontend_snapshot_nonce';
    private const PUBLISH_NONCE_FIELD = 'fmb_publish_frontend_nonce';
    private const NOTICE_TRANSIENT_PREFIX = 'fmb_frontend_notice_';

    private MedalRepository $medals;

    private FrontendPublicationRepository $publication;

    private FileLogger $logger;

    private FrontendAdminRenderer $renderer;

    private string $pageHook = '';

    public function __construct(
        MedalRepository $medals,
        FrontendPublicationRepository $publication,
        FileLogger $logger
    ) {
        $this->medals = $medals;
        $this->publication = $publication;
        $this->logger = $logger;
        $this->renderer = new FrontendAdminRenderer();
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::VISIBILITY_ACTION, [$this, 'handleSaveVisibility']);
        add_action('admin_post_' . self::PUBLISH_ACTION, [$this, 'handlePublishSnapshot']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        $this->pageHook = (string) add_submenu_page(
            self::PARENT_SLUG,
            __('Frontend del medallero', 'cannes-festival-medal-tracker'),
            __('Frontend', 'cannes-festival-medal-tracker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        AdminAssets::enqueue($hookSuffix, $this->pageHook, []);
    }

    public function handleSaveVisibility(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para configurar el frontend.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::VISIBILITY_NONCE_ACTION, self::VISIBILITY_NONCE_FIELD);

        $enabled = !empty($_POST['fmb_frontend_enabled']);
        $this->publication->setEnabled($enabled);
        $this->logger->warning(
            'Frontend shortcode visibility changed.',
            [
                'user_id' => get_current_user_id(),
                'enabled' => $enabled,
            ]
        );

        $this->setNotice([
            'message' => $enabled
                ? __('Shortcodes visibles en el frontend.', 'cannes-festival-medal-tracker')
                : __('Shortcodes ocultos en el frontend.', 'cannes-festival-medal-tracker'),
        ]);
        $this->redirectToPage();
    }

    public function handlePublishSnapshot(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para publicar el frontend.', 'cannes-festival-medal-tracker'));
        }

        check_admin_referer(self::PUBLISH_NONCE_ACTION, self::PUBLISH_NONCE_FIELD);

        try {
            $rows = $this->medals->getCountryDetails();
            $changes = $this->publication->getPendingChanges($rows);
            $this->publication->publish($rows);
            $this->logger->warning(
                'Frontend medal snapshot published.',
                [
                    'user_id'          => get_current_user_id(),
                    'published_rows'   => count($rows),
                    'pending_changes'  => count($changes),
                    'published_at'     => $this->publication->getPublishedAt(),
                ]
            );
            $this->setNotice([
                'message' => sprintf(
                    /* translators: 1: country rows, 2: changed country rows. */
                    __('Datos publicados. Paises en frontend: %1$d. Paises con cambios aplicados: %2$d.', 'cannes-festival-medal-tracker'),
                    count($rows),
                    count($changes)
                ),
            ]);
        } catch (Throwable $throwable) {
            $this->logger->exception(
                $throwable,
                [
                    'user_id' => get_current_user_id(),
                    'action'  => self::PUBLISH_ACTION,
                ]
            );
            $this->setNotice([
                'error' => __('No se pudo publicar el medallero del frontend. Revisa el log.', 'cannes-festival-medal-tracker'),
            ]);
        }

        $this->redirectToPage();
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'cannes-festival-medal-tracker'));
        }

        $notice = get_transient($this->noticeTransientKey());
        delete_transient($this->noticeTransientKey());
        $liveRows = $this->medals->getCountryDetails();
        $state = [
            'enabled'         => $this->publication->isEnabled(),
            'published_at'    => $this->publication->getPublishedAt(),
            'published_rows'  => $this->publication->getPublishedRows(),
            'pending_changes' => $this->publication->getPendingChanges($liveRows),
            'live_rows'       => $liveRows,
        ];

        $this->renderer->render($state, is_array($notice) ? $notice : [], $this->viewConfig());
    }

    private function viewConfig(): array
    {
        return [
            'visibility_action'       => self::VISIBILITY_ACTION,
            'visibility_nonce_action' => self::VISIBILITY_NONCE_ACTION,
            'visibility_nonce_field'  => self::VISIBILITY_NONCE_FIELD,
            'publish_action'          => self::PUBLISH_ACTION,
            'publish_nonce_action'    => self::PUBLISH_NONCE_ACTION,
            'publish_nonce_field'     => self::PUBLISH_NONCE_FIELD,
        ];
    }

    private function setNotice(array $notice): void
    {
        set_transient($this->noticeTransientKey(), $notice, MINUTE_IN_SECONDS * 10);
    }

    private function redirectToPage(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    private function noticeTransientKey(): string
    {
        return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
    }
}
