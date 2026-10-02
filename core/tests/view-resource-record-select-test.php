<?php
function load_library($_name) {}
function get_param_value($params, $key, $default = null) { return $params[$key] ?? $default; }
function detect_language_sc() { return 'nl'; }
function resolve_i18n($value, $lang) { return is_array($value) ? ($value[$lang] ?? '') : $value; }
function data_exists($resource) { return $resource === 'categories'; }
function data_read($resource) { return ['first' => ['name' => 'A long category name that must remain fully visible', 'short_name' => 'Kort'], 'second' => ['name' => '<script>unsafe</script>', 'short_name' => 'Andere'], 'translated' => ['name' => ['nl' => 'Natuur', 'en' => 'Nature']]]; }
require __DIR__ . '/../lib/fmt/fmt.php';
require __DIR__ . '/../modules/admin/lib/get-resource-records.php';
require __DIR__ . '/../modules/admin/lib/view-resource-record.php';
function select_view_assert($expected, $actual) { if ($expected !== $actual) { fwrite(STDERR, "FAIL: select labels or escaping differ\n"); exit(1); } }
$field = ['type' => 'select', 'resource' => 'categories', 'multi' => true, 'display_field' => 'name'];
select_view_assert('A long category name that must remain fully visible, &lt;script&gt;unsafe&lt;/script&gt;', view_resource_record_value('select', ['first', 'second'], $field));
select_view_assert('Natuur', view_resource_record_value('select', ['translated'], $field));
$field['display_field'] = 'short_name';
select_view_assert('Kort, Andere', view_resource_record_value('select', ['first', 'second'], $field));
select_view_assert('Kort', view_resource_record_value('select', 'first', $field));
select_view_assert('missing', view_resource_record_value('select', 'missing', $field));
select_view_assert('Redactie', view_resource_record_value('select', ['editor'], ['type' => 'select', 'options' => ['editor' => 'Redactie']]));
echo "Record view select labels, configured display field and escaping passed.\n";
