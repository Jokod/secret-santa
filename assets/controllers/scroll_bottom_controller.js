import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        requestAnimationFrame(() => {
            const top = Math.max(
                document.body.scrollHeight,
                document.documentElement.scrollHeight,
            );
            window.scrollTo({ top, left: 0, behavior: 'auto' });
        });
    }
}
