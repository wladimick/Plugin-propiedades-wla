(() => {
	'use strict';

	const input = document.getElementById('wla-inmo-help-search');
	const topics = Array.from(document.querySelectorAll('[data-wla-help-topic]'));
	const empty = document.getElementById('wla-inmo-help-empty');
	const status = document.getElementById('wla-inmo-help-search-status');

	if (!input || topics.length === 0) {
		return;
	}

	const normalize = (value) => String(value || '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.trim();

	const applyFilter = () => {
		const query = normalize(input.value);
		let visible = 0;

		topics.forEach((topic) => {
			const haystack = normalize(topic.dataset.search);
			const matches = query === '' || haystack.includes(query);
			topic.hidden = !matches;
			if (matches) {
				visible += 1;
			}
		});

		if (empty) {
			empty.hidden = visible !== 0;
		}

		if (status) {
			status.textContent = query === '' ? '' : `${visible} tema${visible === 1 ? '' : 's'} encontrado${visible === 1 ? '' : 's'}.`;
		}
	};

	input.addEventListener('input', applyFilter);
})();
