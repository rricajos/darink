let message = $state('');
let visible = $state(false);
let timer: ReturnType<typeof setTimeout>;

export const toast = {
	get message() { return message; },
	get visible() { return visible; },

	show(msg: string, ms = 2000) {
		clearTimeout(timer);
		message = msg;
		visible = true;
		timer = setTimeout(() => { visible = false; }, ms);
	},

	hide() {
		clearTimeout(timer);
		visible = false;
	}
};
