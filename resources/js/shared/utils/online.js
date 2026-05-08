// Подписка на канал 'online' через Reverb. Серверный таск раз в 10 сек
// пушит событие .online.updated с {count}. На клиенте — плавный tween.
//
// Использование: subscribeOnline(initial, (value) => { state.online = value })

import { getEcho } from '../echo';

export function subscribeOnline(initial, onUpdate) {
	let current = Number(initial) || 0;
	let target = current;
	let rafId = null;

	const tween = () => {
		const diff = target - current;
		if (Math.abs(diff) < 1) {
			current = target;
			onUpdate(Math.round(current));
			rafId = null;
			return;
		}
		current += diff * 0.15;
		onUpdate(Math.round(current));
		rafId = requestAnimationFrame(tween);
	};

	let channel = null;
	try {
		channel = getEcho().channel('online');
		channel.listen('.online.updated', (e) => {
			if (typeof e.count === 'number') {
				target = e.count;
				if (rafId === null) rafId = requestAnimationFrame(tween);
			}
		});
	} catch (e) {
		// если Reverb недоступен — оставляем initial
	}

	return () => {
		try { getEcho().leave('online'); } catch (e) {}
		if (rafId !== null) cancelAnimationFrame(rafId);
	};
}
