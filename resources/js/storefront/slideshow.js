/** Hero slideshow: index only. No autoplay, as the UIkit slideshow it replaces. */
export function slideshow(config) {
    return {
        index: 0,
        count: config.count ?? 1,
        next() {
            this.index = (this.index + 1) % this.count;
        },
        prev() {
            this.index = (this.index - 1 + this.count) % this.count;
        },
        go(index) {
            this.index = index;
        },
    };
}
