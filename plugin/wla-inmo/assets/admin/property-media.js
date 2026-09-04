(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
			return;
		}
		callback();
	}

	function text(node, value) {
		node.textContent = value || '';
		return node;
	}

	function button(label, attribute) {
		var element = document.createElement('button');
		element.type = 'button';
		element.className = 'button button-small';
		element.setAttribute(attribute, '');
		text(element, label);
		return element;
	}

	function attachmentThumbnail(attachment) {
		var sizes = attachment.get('sizes') || {};
		if (sizes.thumbnail && sizes.thumbnail.url) {
			return sizes.thumbnail.url;
		}
		if (sizes.medium && sizes.medium.url) {
			return sizes.medium.url;
		}
		return attachment.get('url') || '';
	}

	function attachmentCanEdit(attachment) {
		var nonces = attachment.get('nonces') || {};
		return Boolean(nonces.update || attachment.get('editLink'));
	}

	function createItem(root, attachment) {
		var id = parseInt(attachment.get('id'), 10);
		if (!id) {
			return null;
		}

		var item = document.createElement('li');
		item.className = 'wla-inmo-property-media__item';
		item.setAttribute('data-wla-media-item', '');
		item.setAttribute('data-attachment-id', String(id));

		var preview = document.createElement('div');
		preview.className = 'wla-inmo-property-media__preview';
		var imageUrl = attachmentThumbnail(attachment);
		if (imageUrl) {
			var image = document.createElement('img');
			image.className = 'wla-inmo-property-media__thumb';
			image.src = imageUrl;
			image.alt = '';
			image.loading = 'lazy';
			preview.appendChild(image);
		} else {
			var missing = document.createElement('span');
			missing.className = 'wla-inmo-property-media__missing';
			text(missing, root.dataset.labelImage || 'Imagen');
			preview.appendChild(missing);
		}
		item.appendChild(preview);

		var meta = document.createElement('div');
		meta.className = 'wla-inmo-property-media__meta';
		var title = document.createElement('strong');
		text(title, attachment.get('title') || ((root.dataset.labelImage || 'Imagen') + ' #' + id));
		meta.appendChild(title);

		var alt = attachment.get('alt') || '';
		var altLabel = document.createElement('label');
		var inputId = 'wla-inmo-media-alt-' + id;
		altLabel.htmlFor = inputId;
		text(altLabel, root.dataset.labelAlt || 'Texto ALT');
		meta.appendChild(altLabel);

		if (attachmentCanEdit(attachment)) {
			var altInput = document.createElement('input');
			altInput.type = 'text';
			altInput.className = 'regular-text';
			altInput.id = inputId;
			altInput.name = 'wla_inmo_media_alt[' + id + ']';
			altInput.value = alt;
			meta.appendChild(altInput);
		} else {
			var altText = document.createElement('span');
			altText.className = 'description';
			text(altText, alt);
			meta.appendChild(altText);
		}
		item.appendChild(meta);

		var actions = document.createElement('div');
		actions.className = 'wla-inmo-property-media__actions';
		actions.appendChild(button(root.dataset.labelMovePrev || 'Mover antes', 'data-wla-media-move-prev'));
		actions.appendChild(button(root.dataset.labelMoveNext || 'Mover después', 'data-wla-media-move-next'));
		actions.appendChild(button(root.dataset.labelRemove || 'Quitar', 'data-wla-media-remove'));
		item.appendChild(actions);

		return item;
	}

	function init(root) {
		var list = root.querySelector('[data-wla-media-list]');
		var hidden = root.querySelector('[data-wla-media-gallery-input]');
		var counter = root.querySelector('[data-wla-media-count]');
		var add = root.querySelector('[data-wla-media-add]');
		var frame = null;

		if (!list || !hidden) {
			return;
		}

		function items() {
			return Array.prototype.slice.call(list.querySelectorAll('[data-wla-media-item]'));
		}

		function sync() {
			var current = items();
			hidden.value = current.map(function (item) {
				return item.getAttribute('data-attachment-id');
			}).filter(Boolean).join(',');

			current.forEach(function (item, index) {
				var previous = item.querySelector('[data-wla-media-move-prev]');
				var next = item.querySelector('[data-wla-media-move-next]');
				if (previous) {
					previous.disabled = index === 0;
				}
				if (next) {
					next.disabled = index === current.length - 1;
				}
			});

			if (!counter) {
				return;
			}
			if (current.length === 0) {
				text(counter, root.dataset.countEmpty || 'La galería está vacía');
			} else if (current.length === 1) {
				text(counter, root.dataset.countSingular || '1 imagen en la galería');
			} else {
				text(counter, (root.dataset.countPlural || '%d imágenes en la galería').replace('%d', String(current.length)));
			}
		}

		list.addEventListener('click', function (event) {
			var target = event.target;
			if (!(target instanceof Element)) {
				return;
			}
			var item = target.closest('[data-wla-media-item]');
			if (!item || !list.contains(item)) {
				return;
			}

			if (target.closest('[data-wla-media-remove]')) {
				item.remove();
				sync();
				return;
			}

			if (target.closest('[data-wla-media-move-prev]')) {
				var previous = item.previousElementSibling;
				if (previous) {
					list.insertBefore(item, previous);
					sync();
				}
				return;
			}

			if (target.closest('[data-wla-media-move-next]')) {
				var next = item.nextElementSibling;
				if (next) {
					list.insertBefore(next, item);
					sync();
				}
			}
		});

		if (add && window.wp && window.wp.media) {
			add.addEventListener('click', function () {
				if (!frame) {
					frame = window.wp.media({
					title: add.textContent || 'Seleccionar imágenes',
					button: { text: add.textContent || 'Seleccionar imágenes' },
					library: { type: 'image' },
					multiple: true
				});

					frame.on('select', function () {
						var known = {};
						items().forEach(function (item) {
							known[item.getAttribute('data-attachment-id')] = true;
						});

						frame.state().get('selection').each(function (attachment) {
							var id = String(attachment.get('id') || '');
							if (!id || known[id]) {
								return;
							}
							var item = createItem(root, attachment);
							if (item) {
								list.appendChild(item);
								known[id] = true;
							}
						});
						sync();
					});
				}
				frame.open();
			});
		}

		sync();
	}

	ready(function () {
		document.querySelectorAll('[data-wla-property-media]').forEach(init);
	});
}());
