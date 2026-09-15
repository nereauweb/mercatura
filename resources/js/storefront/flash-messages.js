/**
 * Session flash messages (status, success, error) shown as toasts that
 * disappear after a few seconds, like the notifications they replace.
 */
export function flashMessages() {
    return {
        messages: [],
        init() {
            this.messages = JSON.parse(this.$el.dataset.messages || '[]');
            this.messages.forEach((message, index) => {
                setTimeout(() => this.dismiss(index), message.timeout ?? 5000);
            });
        },
        dismiss(index) {
            this.messages.splice(index, 1, { ...this.messages[index], hidden: true });
        },
    };
}
