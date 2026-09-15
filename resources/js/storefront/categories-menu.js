/**
 * "All categories" mega menu: open/close and the active top-level category
 * whose children are shown. Pure local state (no server round-trip).
 */
export function categoriesMenu() {
    return {
        open: false,
        active: 0,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
        select(index) {
            this.active = index;
        },
    };
}
