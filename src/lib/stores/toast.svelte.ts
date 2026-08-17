let message = $state('');
let visible = $state(false);
let action = $state<{ label: string; fn: () => void } | null>(null);
let timer: ReturnType<typeof setTimeout>;

export const toast = {
	get message() { return message; },
	get visible() { return visible; },
	get action() { return action; },

	show(msg: string, opt?: { label: string; fn: () => void }, ms = 5000) {
		clearTimeout(timer);
		message = msg;
		action = opt ?? null;
		visible = true;
		timer = setTimeout(() => { visible = false; action = null; }, ms);
	},

	hide() {
		clearTimeout(timer);
		visible = false;
		action = null;
	}
};
