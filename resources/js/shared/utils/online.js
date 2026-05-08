// Опрос /api/online раз в 10 секунд + плавный переход к новому значению.
// Использование: startOnlinePolling(initial, (value) => { state.online = value })

export function startOnlinePolling(initial, onUpdate, intervalMs = 10000) {
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

	const fetchOnce = async () => {
		try {
			const res = await fetch('/api/online', { headers: { Accept: 'application/json' } });
			if (!res.ok) return;
			const data = await res.json();
			if (typeof data.count === 'number') {
				target = data.count;
				if (rafId === null) rafId = requestAnimationFrame(tween);
			}
		} catch (e) {
			// игнорируем сетевые ошибки
		}
	};

	const timer = setInterval(fetchOnce, intervalMs);

	return () => {
		clearInterval(timer);
		if (rafId !== null) cancelAnimationFrame(rafId);
	};
}
