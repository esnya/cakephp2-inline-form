'use strict';
var Inflector;
(function (module) {
	module.pluralize = function(singular) {
		return singular + 's';
	};
	module.singularize = function(plural) {
		return plural.replace('/s$/', '');
	};
	module.camelize = function(underscored) {
		return underscored.replace(/(^[a-z]|_+[a-z])/g, function (match) {
			if (match.length > 1) match = match.charAt(match.length - 1);
			return match.toUpperCase();
		});
	};
	module.underscore = function(camelCase) {
		return camelCase.replace(/^[A-Z]/, function (match) {
			return match.toLowerCase(match);
		}).replace(/[A-Z]/g, function (match) {
			return '_' + match.toLowerCase(match);
		});
	}
})(Inflector || (Inflector = {}));

(function($) {

    var setInputVal = function() {
        var control = $(this).closest('.if-control');
        var val = control.if('val');
        control.data('old', val);

		var input = control.if('input');

		if (input.is('[type=checkbox]')) {
			input.prop('checked', val);
		} else {
			input.val(val);
		}
    };

	var setByPath = function(target, data, path) {
		if (path === undefined) path = '';
		else path += '.';

		var model = target.data('model');

		for (var key in data) {
			var value = data[key];
			var npath = path + key;


			var t = typeof(value);
			if (t == 'string' || t == 'number' || t == 'boolean' || t == 'Date') {
				target.find('.if-control').filter(function (index, element) {
					var control = $(element);
					return control.data('model') == model && control.data('field') == key;
				}).if('val', value);
			} else {

				var ntarget;
				if (typeof(key) == 'number' || typeof(key) == 'string' && key.match(/^[0-9]+$/)) {
					if (target.length > 0) {
						ntarget = target.closest('.if-table').filter(function (index, element) {
							return $(element).data('model') == model;
						}).find('.if-form:not(.if-template)').filter(function (index, element) {
                            return index == key;
                        });
					}
					if (!ntarget || ntarget.length == 0) {
						var elements = [];
						target.closest('.if-table').each(function (index, element) {
							var table = $(element);
							var template = table.find('.if-form.if-template').clone().removeClass('if-template').if();

							var to = table.find('.if-form:not(.if-template):last');
							if (to.length >= 0) {
								template.insertAfter(to);
							} else {
								template.appendTo(table.find('tbody'));
							}

							elements.push(template[0]);
						});
						ntarget = $(elements);
					}
				} else {
					ntarget = target.find('.if-form:not(.if-template)').filter(function (index, element) {
						return $(element).data('model') == key;
					});
				}

				setByPath(ntarget, value, npath);
			}
		}

		return;
		for (var key in data) {
			var value = data[key];
			var nextPath = (path) ? (path + '.' + key) : key;

            if (value === null) value = '';

			if (key.match(/[0-9]+/)) {
				target.find('.if-table').filter(function (index, element) {
					return $(element).data('model') == path;
				}).each(function (index, element) {
					var table = $(element);
                    var lines = table.find('.if-form');
					var line = lines.filter(function (index, element) {
						return $(element).find('.if-id').if('val') == value.id;
					});

					if (line.length == 0) {
						line = table.find('.if-template').clone().removeClass('if-template');

						if (line.length == 0) return;
                        var html = line.get(0).outerHTML.replace(/{n}/g, lines.length);

                        line = $(html).if();
                        table.find('tbody').append(line);
					}

					for (var ckey in value) {
                        var control = line.find('.if-control').filter(function (index, element) {
                            return $(element).data('field') == ckey;
						});

                        var cvalue = value[ckey];
                        if (cvalue === null) cvalue = '';
                        if (control.if('val') != cvalue) {
                            control.if('val', cvalue);
                        }
					}
				});
			} else {
				var t = typeof(value);
				if (t == 'string' || t == 'number' || t == 'boolean' || t == 'Date') {
					target.find('.if-control').filter(function (index, element) {
						return $(element).data('path') == this;
					}.bind(nextPath)).each(function (index, element) {
						var control = $(element);
						var oldVal = control.if('val');
						if (oldVal != value) control.if('val', value);
					});
				} else {
					setByPath(target, data[key], nextPath);
				}
			}
		}
	};

    var updateImpl = function(url, id, path, val, callback) {
            $.ajax(url, {
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'update',
                    id: id,
                    path: path,
                    value: val
                }
            }).success(function(data, status, xhr) {
                if (callback) callback(data.data);
				setByPath($('body'), data.data);
            }).fail(function(xhr, status, error) {
                if (status == 'parsererror') {
					window.open('', null).document.body.innerHTML = xhr.responseText;
                    console.error(status, error, xhr.responseText);
                } else {
					//window.open('', null).document.body.innerHTML = xhr.responseText;
                    console.error(status, error, JSON.parse(xhr.responseText));
                }
            });
    };

    var update = function() {
        var control = $(this).closest('.if-control');
		var input = control.if('input');
        var val;
	   
		if (input.is('[type=checkbox]')) {
			val = input.prop('checked') ? 1 : 0;
		} else {
			val = input.val();
		}

        if (val != control.data('old')) {
            updateImpl(control.if('url'), control.if('id'), control.if('model') + '.' + control.data('field'), val);
        }
    };

    var _new = function() {
        var button = $(this).closest('.if-new');
        var table = button.closest('table');
        var lines = table.find('.if-form:not(.if-template)');

        var template = table.find('.if-template').clone();
        create.bind(template.html(template.html().replace(/{n}/g, lines.length)))().removeClass('if-template');
        var parentForm = table.closest('.if-form');
        updateImpl(template.if('url'), undefined, template.if('model') + '.' + Inflector.underscore(parentForm.data('model')) + '_id', table.if('id'));
    };

	var _delete = function() {
		if (confirm('本当に削除してよろしいですか？')) {
			var form = $(this).closest('.if-form');

			$.ajax(form.if('url'), {
				type: 'POST',
				dataType: 'JSON',
				data: {
					action: 'delete',
					path: form.if('model'),
						id: form.if('id'),
				}
			}).success(function(data, status, xhr) {
				$('.if-form').filter(function (index, element) {
					var form = $(element);
					return form.if('model') == data.model && form.if('id') == data.id;
				}).remove();
				setByPath($('body'), data.data);
			}).fail(function(xhr, status, error) {
				if (status == 'parsererror') {
					window.open('', null).document.body.innerHTML = xhr.responseText;
					console.error(status, error, xhr.responseText);
				} else {
					//window.open('', null).document.body.innerHTML = xhr.responseText;
					console.error(status, error, JSON.parse(xhr.responseText));
				}
			});
		}
	};

    var create = function() {
        var value = this.find('.if-value');
        var input = this.find('.if-input');

        this.bind('focus', setInputVal);
        input.bind('focus', setInputVal);

        input.bind('blur', update);

		this.bind('animationend', function() {
			$(this).removeClass('if-updated');
		});

		var form = this.closest('.if-form');
        if (form.is('tr')) {
			var deleteButton = form.find('.if-delete');
			if (deleteButton.length && !deleteButton.data('initialized')) {
				deleteButton.bind('click', _delete);
				deleteButton.data('initialized', true);
			}

            var table = this.closest('table');
            if (!table.data('initialized')) {
                table.find('.if-new').bind('click', _new);
                table.data('initialized');
            }
        }

        return this;
    };

	var execHandlers = function(key, handlers, arg) {
		if (!(key in handlers)) {
			key = 'default';
		}
		return handlers[key](arg);
	};

	$.fn.if = function(method, data) {
        if (method === undefined) {
            create.bind(this)();
            return this;
        } else if (method == 'value') {
            return this.find('.if-value');
        } else if (method == 'input') {
            return this.find('.if-input');
        } else if (method == 'form') {
            return this.closest('.if-form');
        } else if (method == 'id') {
			var form = this.if('form');
			var model = form.data('model');
            return form.find('.if-control').filter(function (index, element) {
				var control = $(element);
				return control.if('model') == model && control.data('field') == 'id';
			}).if('val');
        } else if (method == 'url') {
            return this.if('form').data('url');
        } else if (method == 'model') {
            return this.if('form').data('model');
        } else if (method == 'val') {
            if (data === undefined) {
                var val;
                var input = this.if('input');

				execHandlers(this.data('type'), {
					select: function (control) {
						val = control.data('value') || null;
					},
					checkbox: function (control) {
						val = control.data('value') != false;
					},
					default: function (control) {
						val = control.if('value').text();
					}
				}, this);

                return val;
            } else {
				var input = this.if('input');

				var changed = false;

				execHandlers(this.data('type'), {
					select: function (control) {
						data = data || null;
						changed = control.if('val') != data;
						input.val(data);
						control.data('value', data);
						control.if('value').text(input.find(':selected').text());
					},
					checkbox: function (control) {
						data = data != false;
						changed = control.if('val') != data;
						control.data('value', data ? 1 : 0);
						var html = control.if('form').find(data ? '.if-true' : '.if-false').html();
						if (html) control.if('value').html(html);
					},
					multiline: function (control) {
						changed = data != control.if('val');
						var value = control.if('value');
						value.text(data);
						value.html(value.html().replace(/\r\n|\n|\r/g, '<br>\r\n'));
					},
					default: function (control) {
						changed = data != control.if('val');
						control.if('value').text(data);
					}
				}, this);

				if (changed) {
					this.addClass('if-updated');
				}
            }
        }
	};
})(jQuery);

$(function() {
	$('.if-control').if();
});
