/**
 * The personal-links panel on a kit's edit screen, harvested from daan's access-grants.js. It asks
 * the kit's atelier/v1 grant routes and redraws the list from each answer. Every decision is the
 * server's — who may, which kit, which address, the cap — so this file holds no rule of its own,
 * and a request made without it meets the same routes. wp.apiFetch sends WordPress's wp_rest nonce.
 */
(function () {
	'use strict';

	var panel = document.querySelector('.atelier-grants');
	var config = window.atelierGrants;

	if (!panel || !config || !window.wp || !window.wp.apiFetch) {
		return;
	}

	var labels = config.labels;
	var path = '/' + config.namespace + '/grants/' + panel.getAttribute('data-type') + '/';
	var kit = parseInt(panel.getAttribute('data-kit'), 10);
	var list = panel.querySelector('.atelier-grants__rows');
	var template = panel.querySelector('.atelier-grants__template');
	var empty = panel.querySelector('.atelier-grants__empty');
	var sendAll = panel.querySelector('.atelier-grants__send-all');
	var notice = panel.querySelector('.atelier-grants__notice');
	var addresses = panel.querySelector('.atelier-grants__addresses');
	var language = panel.querySelector('.atelier-grants__lang');
	var columns = ['address', 'lang', 'created', 'sent', 'accesses', 'status'];

	function say(text) {
		notice.textContent = text;
	}

	// Text, never markup: an address is a string its recipient chose.
	function draw(grants) {
		list.textContent = '';

		grants.forEach(function (grant) {
			var row = template.content.firstElementChild.cloneNode(true);

			row.setAttribute('data-grant', String(grant.id));
			columns.forEach(function (column) {
				row.querySelector('.atelier-grants__' + column).textContent = String(grant[column]);
			});
			row.querySelectorAll('button').forEach(function (button) {
				button.hidden = !grant.live;
			});
			list.appendChild(row);
		});

		empty.hidden = grants.length > 0;
		sendAll.hidden = !grants.some(function (grant) {
			return grant.live;
		});
	}

	// A refusal is a real 4xx carrying WordPress's REST error; its message is the server's own.
	function ask(action, data, button, done) {
		button.disabled = true;
		say('');

		window.wp
			.apiFetch({ path: path + action, method: 'POST', data: data })
			.then(
				function (answer) {
					draw(answer.grants || []);
					say(done(answer));
				},
				function (error) {
					say((error && error.message) || '');
				}
			)
			.then(function () {
				button.disabled = false;
			});
	}

	function send(ids, button) {
		ask('send', { post_id: kit, grant_ids: ids }, button, function (answer) {
			var text = labels.sent_count.replace('%d', answer.sent.length);

			return answer.failed.length ? text + ' ' + labels.failed.replace('%d', answer.failed.length) : text;
		});
	}

	function liveIds() {
		return Array.prototype.filter
			.call(list.querySelectorAll('tr[data-grant]'), function (row) {
				return !row.querySelector('.atelier-grants__send').hidden;
			})
			.map(function (row) {
				return parseInt(row.getAttribute('data-grant'), 10);
			});
	}

	panel.addEventListener('click', function (event) {
		var button = event.target.closest('button');
		var row = button ? button.closest('tr[data-grant]') : null;
		var id = row ? parseInt(row.getAttribute('data-grant'), 10) : 0;

		if (!button) {
			return;
		}

		if (button.classList.contains('atelier-grants__issue')) {
			var data = { post_id: kit, recipient_emails: addresses.value };

			if (language) {
				data.lang = language.value;
			}

			ask('issue', data, button, function (answer) {
				var text = labels.issued.replace('%d', answer.issued.length);

				if (!answer.rejected.length) {
					addresses.value = '';

					return text;
				}

				return text + ' ' + labels.rejected.replace('%s', answer.rejected.join(', '));
			});
		} else if (button.classList.contains('atelier-grants__send')) {
			send([id], button);
		} else if (button.classList.contains('atelier-grants__send-all')) {
			if (window.confirm(labels.confirm_send_all)) {
				send(liveIds(), button);
			}
		} else if (button.classList.contains('atelier-grants__revoke')) {
			if (window.confirm(labels.confirm_revoke)) {
				ask('revoke', { grant_id: id }, button, function () {
					return labels.revoked_done;
				});
			}
		}
	});
})();
