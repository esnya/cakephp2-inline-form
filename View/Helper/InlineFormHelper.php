<?php
	class InlineFormHelper extends AppHelper {
		public $helpers = array('Html');

		private $script_css_written = false;

		private $form;
		private $forms = [];

		private $table;
		private $tables = [];

		public function create($data, $model, $options = []) {
			if (!$this->script_css_written) {
				$this->Html->script('InlineForm.inlineform', ['inline' => false]);
				$this->Html->css('InlineForm.inlineform', ['inline' => false]);
				$this->script_css_written = true;
			}

			$this->form = new InlineForm($this->Html, $data, $model, $options);
			array_push($this->forms, $this->form);

			return $this->form->create();
		}

		public function control($field, $options = [], $valueOptions = [], $inputOptions = []) {
			return $this->form->control($field, $options, $valueOptions, $inputOptions);
		}

		public function end() {
			$tmp = array_pop($this->forms);
			$form = end($this->forms);
			return $tmp->end();
		}

		public function createTable($model, $fields, $options = []) {
			if ($this->readonly) $options['readonly'] = true;
			$options = Hash::insert($options, 'url', Hash::get($this->form->options, 'data-url'));
			$options = Hash::insert($options, 'form', Hash::merge($this->form->originalOptions, Hash::extract($options, 'form')));
			$this->table = new Table($this->Html, $this->form->data, $model, $fields, $options);
			array_push($this->tables, $this->table);
			return $this->table->create();
		}

		public function thead() {
			return $this->table->thead();
		}

		public function tbody() {
			return $this->table->tbody();
		}

		public function tfoot() {
			return $this->table->tfoot();
		}

		public function endTable() {
			$tmp = array_pop($this->tables);
			$this->table = end($this->tables);
			return $tmp->end();
		}

		public function createSimpleTable($model, $fields, $options = []) {
			return "{$this->createTable($model, $fields, $options)}<thead>{$this->thead()}</thead><tbody>{$this->tbody()}</tbody><tfoot>{$this->tfoot()}</tfoot>{$this->endTable()}";
		}
	}

	class InlineForm {
		public $Html;
		public $data;
		public $model;
		public $originalOptions;
		public $options;
		public $controlOptions;
		public $inputOptions;
		public $valueOptions;
		public $tag;
		public $readonly;
		public $primaryKey;
		public $true;
		public $false;
		public $tftag;

		private static $columnTypesCache = [];
		private static function getColumnType($model, $column) {
			$path = "$model.$column";
			if (Hash::check(InlineForm::$columnTypesCache, $path)) {
				return Hash::get(InlineForm::$columnTypesCache, $path);
			} else {
				try {
					$modelInstance = ClassRegistry::init($model);
					if ($modelInstance) {
						$columnTypes = $modelInstance->getColumnTypes();
						InlineForm::$columnTypesCache[$model] = $columnTypes;
						return Hash::get($columnTypes, $column);
					}
				}catch (Exception $e) {
				} 
			}
		}

		public static function extract(&$options, $path, $default = []) {
			$value = Hash::check($options, $path) ? Hash::extract($options, $path) : $default;
			$options = Hash::remove($options, $path);
			return $value;
		}

		public static function get(&$options, $path, $default = null) {
			$value = Hash::check($options, $path) ? Hash::get($options, $path) : $default;
			$options = Hash::remove($options, $path);
			return $value;
		}

		public function __construct($Html, $data, $model, $options) {
			$this->Html = $Html;
			$this->data = $data;
			$this->model = $model;
			$this->options = $options;

			$this->originalOptions = $options;

			$this->controlOptions = InlineForm::extract($this->options, 'control');
			$this->valueOptions = InlineForm::extract($this->options, 'value');
			$this->inputOptions = InlineForm::extract($this->options, 'input');

			$this->tag = InlineForm::get($this->options, 'tag', 'div');
			$this->readonly = InlineForm::get($this->options, 'readonly', false);
			if ($this->readonly) {
				$this->controlOptions['readonly'] = true;
			}

			$this->true = InlineForm::get($this->options, 'true', __('(T)'));
			$this->false = InlineForm::get($this->options, 'false', __('(F)'));
			$this->tftag = InlineForm::get($this->options, 'tftag', 'div');

			$class = InlineForm::extract($this->options, 'class');
			array_push($class, 'if-form');
			$this->options['class'] = $class;
			$this->options['data-model'] = $model;

			$url = InlineForm::extract($this->options, 'url');
			if (!$url) {
				$url = ['controller' => Inflector::underscore(Inflector::pluralize($model)),  'action' => 'inlineform'];
			}
			if (is_array($url)) {
				if (count($url) == 1) $url = $url[0];
				else $url = $this->Html->url($url);
			}
			$this->options['data-url'] = $url;

			$primaryKey = Hash::extract($this->options, 'primaryKey');
			if (!$primaryKey) {
				$primaryKey = ['id'];
			}
			$this->primaryKey = [];
			foreach ($primaryKey as $key => $value) {
				if (is_numeric($key) && is_string($value)) {
					$this->primaryKey[$value] = [];
				} else if (is_array($value)) {
					$this->primaryKey[$key] = $value;
				} else {
					$this->primaryKey[$key] = [$value];
				}
			}
			$this->options['data-primarykey'] = json_encode($this->primaryKey);
		}

		public function create() {
			return $this->Html->tag($this->tag, null, $this->options) . $this->Html->tag($this->tftag, $this->true, ['class' => 'if-true if-hidden']) . $this->Html->tag($this->tftag, $this->false, ['class' => 'if-false if-hidden']);
		}

		public function control($field, $options = []) {
			// Merge Options //
			$options = Hash::merge($options, $this->controlOptions);
			$valueOptions = Hash::merge(Hash::extract($options, 'value'), $this->valueOptions);
			$inputOptions = Hash::merge(Hash::extract($options, 'input'), $this->inputOptions);

			$options['data-model'] = $this->model;
			$options['data-field'] = $field;

			// Get Path //
			$path = InlineForm::get($options, 'path');
			if (!$path) {
				if (Hash::check($this->data, $field)) {
					$path = $field;
				} else {
					$path = "{$this->model}.$field";
				}
			}
			$value = Hash::get($this->data, $path);

			// Get Readonly //
			$readonly = InlineForm::get($options, 'readonly');

			// Get Select Options //
			$selectOptions = array_key_exists('options', $options) ? InlineForm::extract($options, 'options') : null;
			$empty = InlineForm::get($options, 'empty');
			if ($empty && !is_string($empty)) {
				$empty = __('(No Select)');
			}

			// Get Control Type //
			$type = InlineForm::get($options, 'type');
			if (!$type) {
				if (is_array($selectOptions)) {
					$type = 'select';
				} else {
					$columnType = InlineForm::getColumnType($this->model, $field);
					$options['data-debug-columnType'] = $columnType;
					switch ($columnType) {
						case 'text': $type = 'multiline'; break;
						case 'integer': $type = 'number'; break;
						default: $type = 'text'; break;
					}
				}
			}
			$options['data-type'] = $type;

			// Generate Input //
			if (!$readonly) {
				// Get Tag //
				$inputTag = InlineForm::get($inputOptions, 'tag');
				if (!$inputTag) {
					switch ($type) {
						case 'select': $inputTag = 'select'; break;
						case 'multiline': $inputTag = 'textarea'; break;
						default: $inputTag = 'input'; $inputOptions['type'] = $type; break;
					}
				}

				if ($inputTag == 'select') {
					$optionOptions = InlineForm::extract($inputOptions, 'option');
					$optionTag = InlineForm::get($optionOptions, 'tag', 'option');
					$optionValue = InlineForm::get($optionOptions, 'value', 'value');

					$inputInnerHtml = $empty ? $this->Html->tag(
						$optionTag,
						$empty,
						Hash::merge($optionOptions, [$optionValue => ''])
					) : '';
					$inputInnerHtml .= implode(array_map(function ($key, $value) use($optionTag, $optionOptions, $optionValue) {
						return $this->Html->tag(
							$optionTag,
							$value,
							Hash::merge($optionOptions, [$optionValue => $key])
						);
					},
					array_keys($selectOptions),
					$selectOptions
					));
				} else if ($inputTag == 'textarea') {
					$inputInnerHtml = '';
				} else {
					$inputInnerHtml = null;
				}

				$inputClass = Hash::extract($inputOptions, 'class');
				$inputClass[] = 'if-input';
				$inputOptions['class'] = $inputClass;

				if ($type == 'checkbox' && $value) {
					$inputOptions['checked'] = 'checked';
				}

				$innerHtml = $this->Html->tag($inputTag, $inputInnerHtml, $inputOptions);
			} else {
				$innerHtml = '';
			}

			// Generate Value //
			if ($type == 'select') {
				if (array_key_exists($value, $selectOptions)) {
					$valueHtml = $selectOptions[$value];
				} else if ($empty) {
					$valueHtml = $empty;
				} else {
					$valueHtml = '';
				}
				$options['data-value'] = h($value);
			} else if ($type == 'multiline') {
				$valueHtml = nl2br(h($value));
			} else if ($type == 'checkbox') {
				$valueHtml = $value ? $this->true : $this->false;
				$options['data-value'] = $value ? 1 : 0;
			} else {
				$valueHtml = h($value);
			}
			$valueTag = InlineForm::get($valueOptions, 'tag', 'div');
			$valueClass = Hash::extract($valueOptions, 'class');
			$valueClass[] = 'if-value';
			$valueOptions['class'] = $valueClass;
			$innerHtml .= $this->Html->tag(
				$valueTag,
				$valueHtml,
				$valueOptions
			);

			$tag = InlineForm::get($options, 'tag', 'div');
			$class = Hash::extract($options, 'class');
			$class[] = 'if-control';
			if ($readonly) {
				$class[] = 'if-readonly';
			}
			$options['class'] = $class;
			if (!$readonly) {
				$options['tabindex'] = InlineForm::get($options, 'tabindex', 0);
			}

			if (array_key_exists($field, $this->primaryKey)) {
				$this->primaryKey[$field]['wrote'] = true;
			}

			return $this->Html->tag($tag, $innerHtml, $options);
		}

		public function writePrimaryKey($options = []) {
			$options['readonly'] = true;
			$html = '';
			foreach ($this->primaryKey as $key => $value) {
				if (!Hash::get($value, 'wrote')) {
					$html .= $this->control($key, Hash::insert($options, 'class', ['if-hidden'])); 
				}
			}
			return $html;
		}

		public function end($options = []) {
			return $this->writePrimaryKey($options = []) . $this->Html->tag('/' . $this->tag, null);
		}
	}

	class Table {
		private $Html;
		private $data;
		private $tag;
		private $model;
		private $fields;
		private $options;
		private $formOptions;
		private $readonly;

		function __construct($Html, $data, $model, $fields, $options = []) {
			$this->Html = $Html;
			$this->data = $data;
			$this->model = $model;
			$this->fields = array_map(function($key, $value) {
				$field = [];

				if (is_numeric($key) && is_string($value)) {
					$field['path'] = $value;
					$field['options'] = [];
				} else {
					$field['path'] = $key;
					$field['options'] = $value;
				}

				$field['name'] = InlineForm::get($field['options'], 'name');

				return $field;
			}, array_keys($fields), $fields);

			$classes = InlineForm::extract($options, 'class');
			$classes[] = 'if-table';
			$options['class'] = $classes;

			$options['data-model'] = $model;

			$this->readonly = InlineForm::get($options, 'readonly', false);
			$this->tag = InlineForm::get($options, 'tag', 'table');

			$this->formOptions = InlineForm::extract($options, 'form');

			$this->options = $options;
		}

		public function create() {
			return $this->Html->tag($this->tag, null, $this->options);
		}

		public function thead() {
			return $this->Html->tag('tr', implode(array_map(function($key, $value) {
				$name = Hash::get($value, 'name');
				if ($name == null) {
					$path = explode('.', Hash::get($value, 'path'));
					$field = $path[count($path) - 1];
					$name = __(Inflector::humanize($field));
				}
				return $this->Html->tag('th', $name);
			}, array_keys($this->fields), $this->fields)));
		}

		private function tr($basePath, $data, $options = []) {
			if ($this->readonly) $options['readonly'] = true;
			$options = Hash::merge(
				Hash::merge(
					$this->formOptions,
					$options 
				),
				[
				'tftag' => 'td',
				'tag' => 'tr',
				'url' => Hash::extract($this->options, 'url'),
				]
			);
			$form = new InlineForm($this->Html, $data, $this->model, $options);

			$is_first = true;
			return $form->create() . implode(array_map(function($field) use($basePath, $form, &$is_first) {
				if ($is_first && !$this->readonly) {
					$button = '<button  onclick="return false;" class="btn btn-danger btn-xs if-delete pull-right"><span class="glyphicon glyphicon-remove"></span></button>';
					$is_first = false;
				} else {
					$button = '';
				}

				$path = $field['path'];
				$spath = explode('.', $path);
				$options = $field['options'];
				$options['data-field'] = end($spath);
				return $this->Html->tag('td', $button . $form->control($path, $options)); 
			}, $this->fields)) . $form->writePrimaryKey(['tag' => 'td']) . $form->end();
		}

		public function tbody() {
			$items = Hash::extract($this->data, $this->model . '.{n}');
			return implode(array_map(function($key, $value) {
				return $this->tr($this->model . '.' . $key, $value);
			}, array_keys($items), $items));
		}

		public function tfoot() {
			if ($this->readonly) return '';
			return $this->tr('{n}', [], ['id' => '{n}', 'class' => 'if-template'])
			. $this->Html->tag(
				'tr',
				$this->Html->tag(
					'td',
					$this->Html->tag('button', '<span class="glyphicon glyphicon-plus"></span>', ['class' => 'if-new btn btn-default btn-xs pull-right', 'onclick' => 'return false;']),
					['colspan' => count($this->fields) + 1]
				)
			);
		}

		public function end() {
			return $this->Html->tag('/' . $this->tag);
		}
	}
