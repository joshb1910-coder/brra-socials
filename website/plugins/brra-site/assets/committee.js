/* Committee area: auto-save while taking minutes, extra action rows, media report helper. */
(function () {
	// Live minutes: save a few seconds after typing stops.
	var live = document.getElementById('bc-live');
	if (live) {
		var timer = null, saving = false, again = false, label = document.getElementById('bc-saved');
		var save = function () {
			if (saving) { again = true; return; }
			saving = true;
			label.textContent = 'saving…';
			var data = new FormData(live);
			data.set('ajax', '1');
			data.delete('op');
			fetch(live.getAttribute('data-ajax'), { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j || !j.success) { throw new Error('save'); }
					label.textContent = 'saved at ' + j.data.saved;
					// Give new action rows their IDs so they aren't added twice.
					Object.keys(j.data.actions || {}).forEach(function (key) {
						var ids = j.data.actions[key];
						var rows = live.querySelectorAll('#item-' + key + ' .bc-action-row');
						var n = 0;
						rows.forEach(function (row) {
							var text = row.querySelector('input[type=text]');
							if (text && text.value.trim() !== '' && n < ids.length) {
								row.querySelector('input[type=hidden]').value = ids[n++];
							}
						});
					});
				})
				.catch(function () { label.textContent = 'not saved, check your signal'; })
				.then(function () { saving = false; if (again) { again = false; save(); } });
		};
		var later = function () { clearTimeout(timer); label.textContent = 'typing…'; timer = setTimeout(save, 1500); };
		live.addEventListener('input', later);
		live.addEventListener('change', function () { clearTimeout(timer); save(); countPresent(); });
		var countPresent = function () {
			var q = document.getElementById('bc-quorum');
			if (q) { q.textContent = live.querySelectorAll('.bc-att input[value=present]:checked').length + ' present'; }
		};
		live.addEventListener('click', function (e) {
			var b = e.target.closest('.bc-add-action');
			if (!b) { return; }
			var box = b.closest('.bc-actions');
			var rows = box.querySelectorAll('.bc-action-row');
			var last = rows[rows.length - 1];
			var copy = last.cloneNode(true);
			var n = rows.length;
			copy.querySelectorAll('input, select').forEach(function (el) {
				el.name = el.name.replace(/\[actions\]\[\d+\]/, '[actions][' + n + ']');
				if (el.type === 'checkbox') { el.checked = false; } else if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
			});
			box.insertBefore(copy, b);
			copy.querySelector('input[type=text]').focus();
		});
	}

	// Media report: cards with changes, and a drafted summary.
	var media = document.getElementById('bc-media');
	if (media) {
		var num = function (v) { return parseInt(String(v || '').replace(/[^0-9]/g, ''), 10) || 0; };
		var has = function (v) { return String(v || '').replace(/[^0-9]/g, '') !== ''; };
		var get = function (p, f, prev) {
			var el = media.querySelector('[data-p="' + p + '"][data-f="' + f + '"]');
			return el ? (prev ? el.getAttribute('data-prev') : el.value) : '';
		};
		var total = function (f, prev) {
			var t = 0, any = false;
			['instagram', 'facebook', 'x'].forEach(function (p) { var v = get(p, f, prev); if (has(v)) { any = true; } t += num(v); });
			return any ? t : null;
		};
		var fmt = function (n) { return n.toLocaleString('en-GB'); };
		var pill = function (now, then) {
			if (then === null || now === null) { return ''; }
			var d = now - then;
			if (!d) { return '<span class="bc-pill">No change</span>'; }
			return '<span class="bc-pill ' + (d > 0 ? 'bc-g' : 'bc-r') + '">' + (d > 0 ? '▲ ' : '▼ ') + fmt(Math.abs(d)) + '</span>';
		};
		var cards = document.getElementById('bc-media-cards');
		var draw = function () {
			var html = '';
			[['Posts', 'posts'], ['Views', 'views'], ['Likes', 'likes'], ['Followers', 'followers']].forEach(function (c) {
				var now = total(c[1], false), then = total(c[1], true);
				html += '<div><span class="bc-muted">' + c[0] + '</span><strong>' + (now === null ? '–' : fmt(now)) + '</strong>' + pill(now, then) + '</div>';
			});
			var w = get('web', 'visits', false), wp = get('web', 'visits', true);
			html += '<div><span class="bc-muted">Website visits</span><strong>' + (has(w) ? fmt(num(w)) : '–') + '</strong>' + pill(has(w) ? num(w) : null, has(wp) ? num(wp) : null) + '</div>';
			cards.innerHTML = html;
		};
		media.addEventListener('input', draw);
		draw();
		document.getElementById('bc-draft').addEventListener('click', function () {
			var bits = [];
			var change = function (now, then, word) {
				if (now === null) { return ''; }
				if (then === null || then === 0) { return fmt(now) + ' ' + word; }
				var pc = Math.round((now - then) / then * 100);
				return fmt(now) + ' ' + word + (pc === 0 ? ', the same as last month' : ', ' + (pc > 0 ? 'up ' : 'down ') + Math.abs(pc) + '% on last month (' + fmt(then) + ')');
			};
			var posts = total('posts', false), views = total('views', false), likes = total('likes', false), fol = total('followers', false);
			if (posts !== null) { bits.push('We published ' + change(posts, total('posts', true), 'posts') + '.'); }
			if (views !== null) { bits.push('They got ' + change(views, total('views', true), 'views') + '.'); }
			if (likes !== null) { bits.push('Likes: ' + change(likes, total('likes', true), 'in total') + '.'); }
			var best = null, bestPc = null;
			[['Instagram', 'instagram'], ['Facebook', 'facebook'], ['X', 'x']].forEach(function (p) {
				var a = get(p[1], 'views', false), b = get(p[1], 'views', true);
				if (has(a) && has(b) && num(b) > 0) {
					var pc = Math.round((num(a) - num(b)) / num(b) * 100);
					if (bestPc === null || pc > bestPc) { bestPc = pc; best = p[0]; }
				}
			});
			if (best && bestPc > 0) { bits.push(best + ' grew most, with views up ' + bestPc + '%.'); }
			if (fol !== null) { bits.push('Followers across the three: ' + change(fol, total('followers', true), 'in total') + '.'); }
			var w = get('web', 'visits', false), wp = get('web', 'visits', true), f = get('web', 'forms', false);
			if (has(w)) { bits.push('Website: ' + change(num(w), has(wp) ? num(wp) : null, 'visits') + (has(f) ? ', and ' + num(f) + ' form sign-ups' : '') + '.'); }
			var bp = document.getElementById('bc-best').value.trim();
			if (bp) { bits.push('Best post of the month: ' + bp + '.'); }
			var box = document.getElementById('bc-summary');
			if (box.value.trim() && !window.confirm('Replace what you have written with a new draft?')) { return; }
			box.value = bits.join(' ');
		});
	}
})();
