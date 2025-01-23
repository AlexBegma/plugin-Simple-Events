<?php
/**
 * Plugin Name: Simple Events
 * Description: Плагин для управления событиями в WordPress.
 * Version: 1.1
 * Author: Ваше Имя
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class SimpleEvents {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'simple_events';

        // Хуки активации плагина
        register_activation_hook(__FILE__, [$this, 'create_table']);

        // Добавление страницы в меню
        add_action('admin_menu', [$this, 'add_menu_page']);

        // Обработка запросов
        add_action('admin_post_simple_events_save', [$this, 'process_form']);
        add_action('admin_post_simple_events_delete', [$this, 'delete_event']);
    }

    /**
     * Создание таблицы для хранения событий
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Добавление страницы меню
     */
    public function add_menu_page() {
        add_menu_page(
            'События',
            'События',
            'manage_options',
            'simple-events',
            [$this, 'render_page'],
            'dashicons-calendar',
            26
        );
    }

    /**
     * Обработка формы добавления события
     */
    public function process_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.');
        }

        check_admin_referer('simple_events_nonce');

        $event_name = sanitize_text_field($_POST['event_name']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $event_description = sanitize_textarea_field($_POST['event_description']);

        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            [
                'event_name' => $event_name,
                'event_date' => $event_date,
                'event_description' => $event_description,
            ],
            ['%s', '%s', '%s']
        );

        if ($result === false) {
            add_settings_error('simple_events', 'db_error', 'Ошибка при сохранении события.');
        } else {
            add_settings_error('simple_events', 'success', 'Событие успешно добавлено.', 'updated');
        }

        wp_redirect(admin_url('admin.php?page=simple-events'));
        exit;
    }

    /**
     * Удаление события
     */
    public function delete_event() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.');
        }

        $event_id = intval($_GET['event_id']);
        check_admin_referer('delete_event_' . $event_id);

        global $wpdb;

        $result = $wpdb->delete($this->table_name, ['id' => $event_id], ['%d']);

        if ($result === false) {
            add_settings_error('simple_events', 'db_error', 'Ошибка при удалении события.');
        } else {
            add_settings_error('simple_events', 'success', 'Событие успешно удалено.', 'updated');
        }

        wp_redirect(admin_url('admin.php?page=simple-events'));
        exit;
    }

    /**
     * Отображение страницы управления событиями
     */
    public function render_page() {
        global $wpdb;
        $events = $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY event_date ASC");

        ?>
        <div class="wrap">
            <h1>События</h1>
            <?php settings_errors('simple_events'); ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="simple_events_save">
                <?php wp_nonce_field('simple_events_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="event_name">Название события</label></th>
                        <td><input type="text" id="event_name" name="event_name" required></td>
                    </tr>
                    <tr>
                        <th><label for="event_date">Дата события</label></th>
                        <td><input type="date" id="event_date" name="event_date" required></td>
                    </tr>
                    <tr>
                        <th><label for="event_description">Описание события</label></th>
                        <td><textarea id="event_description" name="event_description" rows="5" required></textarea></td>
                    </tr>
                </table>
                <p><input type="submit" class="button-primary" value="Добавить событие"></p>
            </form>

            <h2>Список событий</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Дата</th>
                        <th>Описание</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events): ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo esc_html($event->event_name); ?></td>
                                <td><?php echo esc_html($event->event_date); ?></td>
                                <td><?php echo esc_html($event->event_description); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=simple_events_delete&event_id=' . $event->id), 'delete_event_' . $event->id); ?>" class="button button-secondary">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Событий пока нет.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Инициализация плагина
new SimpleEvents();


// Что изменено
// ООП-подход: Весь функционал плагина собран в класс SimpleEvents.
// Обработка ошибок SQL: Проверяется результат работы $wpdb->insert и $wpdb->delete. При ошибках выводятся сообщения через add_settings_error.
// Разделение логики:
// Метод process_form обрабатывает форму.
// Метод render_page отвечает за отображение интерфейса.
// Сообщения об ошибках и успехах: Используется стандартный механизм add_settings_error и settings_errors для вывода уведомлений.
// Использование admin_post для обработки запросов: Это позволяет надежно разделить обработку данных и отображение страницы.
// Установка
// Скопировать код в файл simple-events.php в директорию wp-content/plugins/simple-events.
// Активировать плагин через панель администрирования WordPress.
// Теперь плагин полностью готов к использованию!