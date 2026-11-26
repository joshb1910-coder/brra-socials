/* Committee area: page changes without a full reload, the menu, auto-save while taking minutes, media report helper, expenses form. */
(function () {
	var initLive = function () {
	// Live minutes: save a few seconds after typing stops.
	var live = document.getElementById('bc-live');
	if (live && !live.dataset.ready) {
		live.dataset.ready = '1';
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
			var att = live.querySelector('.bc-att');
			var rows = live.querySelectorAll('.bc-att-list tr');
			var c = { present: 0, apologies: 0, absent: 0 }, marked = 0;
			rows.forEach(function (r) { var x = r.querySelector('input:checked'); if (x) { marked++; c[x.value]++; } });
			if (q) { q.textContent = c.present + ' present'; }
			if (!att) { return false; }
			var sum = att.querySelector('.bc-att-sum'), tog = att.querySelector('.bc-att-toggle');
			var all = rows.length > 0 && marked === rows.length;
			sum.textContent = 'All ' + rows.length + ' marked: ' + c.present + ' present, ' + c.apologies + ' apologies, ' + c.absent + ' absent.';
			sum.hidden = !all;
			tog.hidden = !all;
			if (!all) { att.classList.remove('is-folded'); }
			return all;
		};
		if (countPresent()) { live.querySelector('.bc-att').classList.add('is-folded'); }
		live.querySelector('.bc-att-toggle').addEventListener('click', function () {
			var att = live.querySelector('.bc-att');
			att.classList.toggle('is-folded');
			this.textContent = att.classList.contains('is-folded') ? 'Change' : 'Done';
		});
		live.addEventListener('change', function (e) {
			if (e.target.closest('.bc-att-list') && countPresent()) {
				var att = live.querySelector('.bc-att');
				att.classList.add('is-folded');
				att.querySelector('.bc-att-toggle').textContent = 'Change';
			}
		});

		live.addEventListener('click', function (e) {
			var nx = e.target.closest('.bc-next');
			if (nx) {
				var item = nx.closest('.bc-item'), next = item && item.nextElementSibling;
				while (next && !next.classList.contains('bc-item')) { next = next.nextElementSibling; }
				if (next) {
					next.scrollIntoView({ behavior: 'smooth', block: 'start' });
					var fld = next.querySelector('textarea, input[type=text]');
					if (fld) { setTimeout(function () { fld.focus({ preventScroll: true }); }, 400); }
				}
				return;
			}
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

	};

	var initMedia = function () {
	// Media report: cards with changes, and a drafted summary.
	var media = document.getElementById('bc-media');
	if (media && !media.dataset.ready) {
		media.dataset.ready = '1';
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
	};

	// Expenses: the card picker only shows for business card payments.
	var initPay = function () {
		document.querySelectorAll('.bc-pay').forEach(function (f) {
			if (f.dataset.ready) { return; }
			f.dataset.ready = '1';
			var sync = function () {
				var c = f.querySelector('input[name=type]:checked');
				var card = !!c && c.value === 'card';
				f.querySelectorAll('.bc-only-card').forEach(function (el) {
					el.hidden = !card;
					el.querySelectorAll('select').forEach(function (s) { s.disabled = !card; });
				});
			};
			f.addEventListener('change', sync);
			sync();
		});
	};

	// Tables: label each cell so rows can stack on a phone.
	var initTables = function (root) {
		root.querySelectorAll('table.bc-table').forEach(function (t) {
			var heads = t.querySelectorAll('thead th');
			if (!heads.length || t.classList.contains('bc-stackable')) { return; }
			var labels = [].map.call(heads, function (h) { return h.textContent.trim(); });
			t.classList.add('bc-stackable');
			t.querySelectorAll('tbody tr').forEach(function (tr) {
				[].forEach.call(tr.children, function (td, i) {
					if (labels[i] && !td.hasAttribute('data-label') && td.tagName === 'TD' && !td.hasAttribute('colspan')) { td.setAttribute('data-label', labels[i]); }
				});
			});
		});
	};

	// Menu: groups remember being open, and the phone menu button.
	var initNav = function () {
		var side = document.getElementById('bc-side');
		if (!side || side.dataset.ready) { return; }
		side.dataset.ready = '1';
		var key = 'bcNavOpen', saved = [];
		try { saved = JSON.parse(window.localStorage.getItem(key) || '[]'); } catch (x) { saved = []; }
		var groups = side.querySelectorAll('details.bc-nav-grp');
		groups.forEach(function (d) {
			if (!d.hasAttribute('data-cur') && saved.indexOf(d.getAttribute('data-g')) > -1) { d.open = true; }
			d.addEventListener('toggle', function () {
				var open = [].filter.call(groups, function (x) { return x.open && !x.hasAttribute('data-cur'); }).map(function (x) { return x.getAttribute('data-g'); });
				try { window.localStorage.setItem(key, JSON.stringify(open)); } catch (x) { /* Private browsing. */ }
			});
		});
		var btn = side.querySelector('.bc-menu-btn');
		if (btn) {
			btn.addEventListener('click', function () {
				var o = side.classList.toggle('is-open');
				btn.setAttribute('aria-expanded', o ? 'true' : 'false');
			});
		}
	};

	var initAll = function (root) {
		initNav();
		initLive();
		initMedia();
		initPay();
		initTables(root || document);
	};

	// Changing page: swap the panel under the header instead of reloading the whole page.
	var bc = document.querySelector('.bc');
	var base = bc ? bc.getAttribute('data-base') : null;
	var downloads = ['file', 'word', 'emails_csv', 'expense_file', 'expenses_csv', 'expenses_zip'];
	var bar = null, busy = false;
	var progress = function (on) {
		if (!bar) {
			bar = document.createElement('div');
			bar.className = 'bc-loading';
			document.body.appendChild(bar);
		}
		bar.classList.toggle('is-on', on);
	};
	var ours = function (url) {
		if (url.origin !== window.location.origin) { return false; }
		if (base && url.pathname === base) { return !url.searchParams.get('print'); }
		if (/\/admin-post\.php$/.test(url.pathname) && url.searchParams.get('action') === 'brra_c') { return downloads.indexOf(url.searchParams.get('do')) < 0; }
		return false;
	};
	var cleanUrl = function (u) {
		var c = new URL(u, window.location.href);
		c.searchParams.delete('done');
		return c.href;
	};
	var swap = function (html, url, push) {
		var doc = new DOMParser().parseFromString(html, 'text/html');
		var main = doc.getElementById('bc-main'), side = doc.getElementById('bc-side');
		var oldMain = document.getElementById('bc-main'), oldSide = document.getElementById('bc-side');
		if (!main || !oldMain) { return false; }
		var wasOpen = oldSide && oldSide.classList.contains('is-open');
		oldMain.replaceWith(main);
		if (side && oldSide) { oldSide.replaceWith(side); }
		document.title = doc.title;
		if (push) { window.history.pushState({ bc: 1 }, '', cleanUrl(url)); } else { window.history.replaceState({ bc: 1 }, '', cleanUrl(url)); }
		initAll(main);
		if (wasOpen) { document.getElementById('bc-side').classList.remove('is-open'); }
		var hash = new URL(url, window.location.href).hash;
		var target = hash && document.getElementById(hash.slice(1));
		if (target) {
			target.scrollIntoView();
		} else {
			var top = document.querySelector('.bc').getBoundingClientRect().top + window.scrollY - 16;
			if (window.scrollY > top) { window.scrollTo(0, Math.max(0, top)); }
		}
		main.setAttribute('tabindex', '-1');
		main.focus({ preventScroll: true });
		return true;
	};
	var load = function (url, opts, push, fallback) {
		if (busy) { return; }
		busy = true;
		progress(true);
		fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {}))
			.then(function (r) {
				var type = r.headers.get('content-type') || '';
				if (type.indexOf('text/html') < 0) { window.location.href = r.url; return null; }
				return r.text().then(function (t) { return { text: t, url: r.url }; });
			})
			.then(function (res) {
				if (res && !swap(res.text, res.url, push)) {
					document.open();
					document.write(res.text);
					document.close();
				}
			})
			.catch(function () { if (fallback) { fallback(); } else { window.location.href = url; } })
			.then(function () { busy = false; progress(false); });
	};
	if (bc) {
		document.documentElement.classList.add('bc-js');
		window.history.replaceState({ bc: 1 }, '', cleanUrl(window.location.href));
		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
			var a = e.target.closest('a[href]');
			if (!a || !a.closest('.bc') || a.target === '_blank' || a.hasAttribute('download') || a.classList.contains('bc-dl') || a.hasAttribute('data-ext')) { return; }
			var url = new URL(a.href, window.location.href);
			if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) { return; }
			if (!ours(url)) { return; }
			e.preventDefault();
			load(url.href, null, true);
		});
		document.addEventListener('submit', function (e) {
			var f = e.target;
			if (e.defaultPrevented || !f.closest || !f.closest('.bc')) { return; }
			var method = (f.getAttribute('method') || 'get').toLowerCase();
			var action = new URL(f.getAttribute('action') || window.location.href, window.location.href);
			var sub = e.submitter;
			var data;
			try { data = new FormData(f, sub); } catch (x) { data = new FormData(f); if (sub && sub.name) { data.append(sub.name, sub.value); } }
			if (method === 'get') {
				action.search = new URLSearchParams(data).toString();
				if (!ours(action)) { return; }
				e.preventDefault();
				load(action.href, null, true);
				return;
			}
			if (!/\/admin-post\.php$/.test(action.pathname) || downloads.indexOf(String(data.get('do'))) > -1) { return; }
			e.preventDefault();
			load(action.href, { method: 'POST', body: data }, true, function () { HTMLFormElement.prototype.submit.call(f); });
		});
		window.addEventListener('popstate', function () {
			if (window.location.pathname === base) { load(window.location.href, null, false); }
		});
	}
	initAll(document);
})();
