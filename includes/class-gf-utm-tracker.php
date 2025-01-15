<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_UTM_Tracker {
    private static $instance = null;
    private $utm_parameters = [
        'utm_source' => 'Source',
        'utm_medium' => 'Medium',
        'utm_campaign' => 'Campaign',
        'utm_term' => 'Term',
        'utm_content' => 'Content'
    ];
    private $debug_mode = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize debug mode
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        // Add filters for all form-related actions
        add_filter('gform_pre_render', [$this, 'add_utm_fields']);
        add_filter('gform_pre_process', [$this, 'add_utm_fields']);
        add_filter('gform_pre_validation', [$this, 'add_utm_fields']);
        add_filter('gform_admin_pre_render', [$this, 'add_utm_fields']);
        add_filter('gform_entry_field_value', [$this, 'maybe_save_utm_value'], 10, 4);

        // Notification filters
        add_filter('gform_notification', [$this, 'add_utm_to_notification'], 10, 3);
        add_filter('gform_pre_send_email', [$this, 'modify_email_content'], 10, 4);
        add_filter('gform_custom_merge_tags', [$this, 'add_utm_merge_tags'], 10, 4);
        add_filter('gform_notification_conditional_logic_fields', [$this, 'add_utm_conditional_fields']);

        // Debug logging
        if ($this->debug_mode) {
            add_action('gform_pre_submission_filter', [$this, 'log_utm_parameters']);
            add_action('gform_after_submission', [$this, 'log_submission'], 10, 2);
        }
    }

    public function add_utm_fields($form) {
        if (!is_array($form) || empty($form['fields'])) {
            return $form;
        }

        if ($this->debug_mode) {
            GFCommon::log_debug(__METHOD__ . '(): Starting to add UTM fields to form #' . $form['id']);
        }

        // Get the highest field ID currently in the form
        $max_field_id = 0;
        foreach ($form['fields'] as $field) {
            if (intval($field->id) > $max_field_id) {
                $max_field_id = intval($field->id);
            }
        }

        foreach ($this->utm_parameters as $param => $label) {
            $field_exists = false;
            foreach ($form['fields'] as $field) {
                if (strpos($field->cssClass, 'utm_param_' . $param) !== false) {
                    $field_exists = true;
                    break;
                }
            }

            if (!$field_exists) {
                $max_field_id++;
                $field = new GF_Field_Hidden();
                $field->id = $max_field_id;
                $field->formId = $form['id'];
                $field->label = $label;
                $field->cssClass = 'utm_param_' . $param;
                $field->adminLabel = 'UTM ' . $label;
                $field->inputName = $param;

                // Set the field value from URL parameter
                $value = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
                $field->defaultValue = $value;

                if ($this->debug_mode) {
                    GFCommon::log_debug(__METHOD__ . "(): Adding UTM field {$param} with ID {$max_field_id}");
                }

                $form['fields'][] = $field;
            }
        }

        return $form;
    }

    public function maybe_save_utm_value($value, $entry, $field, $form) {
        if (strpos($field->cssClass, 'utm_param_') !== false) {
            foreach ($this->utm_parameters as $param => $label) {
                if (strpos($field->cssClass, 'utm_param_' . $param) !== false) {
                    $value = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
                    if ($this->debug_mode) {
                        GFCommon::log_debug(__METHOD__ . "(): Saving UTM value for {$param}: {$value}");
                    }
                    break;
                }
            }
        }
        return $value;
    }

    public function log_utm_parameters($form) {
        GFCommon::log_debug('UTM Parameters for form #' . $form['id'] . ':');
        foreach ($this->utm_parameters as $param => $label) {
            $value = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : 'Not set';
            GFCommon::log_debug($label . ': ' . $value);
        }
        return $form;
    }

    public function log_submission($entry, $form) {
        GFCommon::log_debug(__METHOD__ . '(): Form submission with UTM parameters:');
        foreach ($this->utm_parameters as $param => $label) {
            foreach ($form['fields'] as $field) {
                if (strpos($field->cssClass, 'utm_param_' . $param) !== false) {
                    $value = rgar($entry, $field->id);
                    GFCommon::log_debug("UTM {$label}: {$value}");
                }
            }
        }
    }

    public function add_utm_merge_tags($merge_tags, $form_id, $fields, $element_id) {
        foreach ($this->utm_parameters as $param => $label) {
            $merge_tags[] = array(
                'label' => 'UTM ' . $label,
                'tag'   => '{' . $param . '}'
            );
        }
        return $merge_tags;
    }

    public function add_utm_conditional_fields($fields) {
        foreach ($this->utm_parameters as $param => $label) {
            $fields[] = array(
                'id' => $param,
                'label' => 'UTM ' . $label,
                'type' => 'utm'
            );
        }
        return $fields;
    }

    public function add_utm_to_notification($notification, $form, $entry) {
        // UTM parameters will appear in the standard form submission table
        // No need to add them separately to the notification
        return $notification;
    }

    public function modify_email_content($email, $message_format, $notification, $entry) {
        // We're no longer adding a separate UTM section since the UTM fields
        // will appear in the main form submission table automatically
        return $email;
    }
}