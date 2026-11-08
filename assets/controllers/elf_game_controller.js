import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        duration: { type: Number, default: 60 },
        storageKey: { type: String, default: 'santa-elf-best-score' },
        elfUrl: String,
        giftUrls: Array,
        musicUrl: String,
        catchSoundUrl: String,
        startUrl: String,
        scoreUrl: String,
        csrfToken: String,
        bestScore: { type: Number, default: 0 },
    };

    connect() {
        this.playing = false;
        this.starting = false;
        this.score = 0;
        this.elapsed = 0;
        this.sessionToken = null;
        this.startedAtMs = null;
        this.startAbort = null;
        this.spawnTimer = null;
        this.burstTimers = [];
        this.countdownTimer = null;
        this.overlay = null;
        this.elf = null;
        this.elfHost = null;
        this.elfWrap = null;
        this.audio = null;
        this.catchSound = null;
        this.snowTimer = null;
        this.bestScore = Math.max(this.bestScoreValue, this.readBestScore());
        this.placeHiddenElf();
    }

    disconnect() {
        this.teardown();
    }

    isCoarsePointer() {
        return window.matchMedia('(hover: none), (pointer: coarse)').matches
            || window.matchMedia('(max-width: 720px)').matches;
    }

    placeHiddenElf() {
        this.removeHiddenElf();

        const candidates = Array.from(document.querySelectorAll(
            '.budget-callout, .panel',
        )).filter((el) => el.getClientRects().length > 0);

        if (candidates.length === 0) {
            return;
        }

        const host = candidates[Math.floor(Math.random() * candidates.length)];
        if (!host.parentNode) {
            return;
        }

        // Left/right peeks are often clipped by body overflow-x: clip on mobile.
        const peeks = this.isCoarsePointer()
            ? ['elf-peek-top', 'elf-peek-bottom']
            : ['elf-peek-top', 'elf-peek-right', 'elf-peek-bottom', 'elf-peek-left'];
        const peek = peeks[Math.floor(Math.random() * peeks.length)];

        const wrap = document.createElement('div');
        wrap.className = 'elf-peek-slot';

        const elf = document.createElement('button');
        elf.type = 'button';
        elf.className = `elf-hideout ${peek}`;
        elf.setAttribute('aria-label', 'Un lutin se cache ici');

        const img = document.createElement('img');
        img.src = this.elfUrlValue;
        img.alt = '';
        img.width = 72;
        img.height = 72;
        img.decoding = 'async';
        img.draggable = false;
        elf.appendChild(img);

        const open = (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.startGame();
        };
        // pointerdown is more reliable than click alone on iOS Safari.
        elf.addEventListener('pointerdown', open);
        elf.addEventListener('click', open);

        host.parentNode.insertBefore(wrap, host);
        wrap.appendChild(elf);
        wrap.appendChild(host);
        host.classList.add('elf-host');

        this.elfWrap = wrap;
        this.elfHost = host;
        this.elf = elf;
    }

    removeHiddenElf() {
        if (this.elf) {
            this.elf.remove();
            this.elf = null;
        }

        if (this.elfHost) {
            this.elfHost.classList.remove('elf-host');
        }

        if (this.elfWrap && this.elfHost && this.elfWrap.parentNode) {
            this.elfWrap.parentNode.insertBefore(this.elfHost, this.elfWrap);
            this.elfWrap.remove();
        } else if (this.elfWrap) {
            this.elfWrap.remove();
        }

        this.elfWrap = null;
        this.elfHost = null;
    }

    async startGame() {
        if (this.playing || this.starting) {
            return;
        }

        this.removeHiddenElf();
        this.starting = true;
        this.score = 0;
        this.elapsed = 0;
        this.secondsLeft = this.durationValue;
        this.startedAtMs = null;
        this.renderOverlay({ loading: true });
        this.updateHud();

        const session = await this.beginServerSession();
        if (!this.starting) {
            // User closed the overlay while the session request was in flight.
            return;
        }

        if (!session?.sessionToken) {
            this.showLoadingError();
            window.setTimeout(() => {
                if (!this.starting) {
                    return;
                }
                this.starting = false;
                this.destroyOverlay();
                this.placeHiddenElf();
            }, 1400);
            return;
        }

        this.sessionToken = session.sessionToken;
        if (typeof session.duration === 'number' && session.duration > 0) {
            this.durationValue = session.duration;
            this.secondsLeft = session.duration;
        }

        this.starting = false;
        this.playing = true;
        this.startedAtMs = Date.now();
        this.hideLoading();
        this.updateHud();
        this.startMusic();
        this.startSnow();
        this.countdownTimer = window.setInterval(() => this.tick(), 250);
        this.scheduleSpawn();
    }

    async beginServerSession() {
        if (!this.startUrlValue || !this.csrfTokenValue) {
            return null;
        }

        this.startAbort?.abort();
        this.startAbort = new AbortController();

        try {
            const response = await fetch(this.startUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                },
                credentials: 'same-origin',
                signal: this.startAbort.signal,
            });
            if (!response.ok) {
                return null;
            }
            return await response.json();
        } catch (error) {
            if (error?.name === 'AbortError') {
                return null;
            }
            return null;
        } finally {
            this.startAbort = null;
        }
    }

    hideLoading() {
        this.overlay?.querySelector('[data-elf-loading]')?.remove();
        this.overlay?.classList.remove('is-loading');
    }

    showLoadingError() {
        const loading = this.overlay?.querySelector('[data-elf-loading]');
        if (!loading) {
            return;
        }
        loading.classList.add('is-error');
        loading.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <p>Impossible de démarrer la partie.</p>
        `;
    }

    tick() {
        if (!this.playing || this.startedAtMs === null) {
            return;
        }

        const elapsedMs = Date.now() - this.startedAtMs;
        this.elapsed = Math.floor(elapsedMs / 1000);
        this.secondsLeft = Math.max(0, this.durationValue - Math.ceil(elapsedMs / 1000));
        this.updateHud();

        if (elapsedMs >= this.durationValue * 1000) {
            this.endGame();
        }
    }

    scheduleSpawn() {
        if (!this.playing) {
            return;
        }

        const progress = Math.min(1, this.elapsed / this.durationValue);
        const delay = Math.max(160, Math.round(1000 - progress * 820));
        const burst = 1 + Math.floor(progress * 4);

        for (let i = 0; i < burst; i += 1) {
            const timer = window.setTimeout(() => this.spawnGift(), i * 70);
            this.burstTimers.push(timer);
        }

        this.spawnTimer = window.setTimeout(() => this.scheduleSpawn(), delay);
    }

    giftSources() {
        if (Array.isArray(this.giftUrlsValue) && this.giftUrlsValue.length > 0) {
            return this.giftUrlsValue;
        }

        return [this.elfUrlValue].filter(Boolean);
    }

    spawnGift() {
        if (!this.playing || !this.playfield) {
            return;
        }

        const urls = this.giftSources();
        const src = urls[Math.floor(Math.random() * urls.length)];
        if (!src) {
            return;
        }

        const gift = document.createElement('button');
        gift.type = 'button';
        gift.className = 'elf-gift';
        gift.style.left = `${6 + Math.random() * 82}%`;
        gift.setAttribute('aria-label', 'Cadeau');

        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        img.width = 72;
        img.height = 72;
        img.decoding = 'async';
        img.draggable = false;
        gift.appendChild(img);

        const fallMs = 2800 + Math.random() * 2200;
        const rotFrom = Math.round(-50 + Math.random() * 100);
        const rotTo = rotFrom + Math.round(-80 + Math.random() * 160);
        gift.style.setProperty('--elf-fall-ms', `${Math.round(fallMs)}ms`);
        gift.style.setProperty('--elf-rot-from', `${rotFrom}deg`);
        gift.style.setProperty('--elf-rot-to', `${rotTo}deg`);

        const catchGift = (event) => {
            if (!this.playing || gift.dataset.caught === '1') {
                return;
            }
            gift.dataset.caught = '1';
            const rect = gift.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            gift.remove();
            this.score += 1;
            this.updateHud();
            this.playCatchSound();
            this.spawnCatchEffect(x, y);
        };

        gift.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            event.stopPropagation();
            catchGift(event);
        });

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) {
            gift.classList.add('elf-gift-static');
            gift.style.top = `${12 + Math.random() * 70}%`;
            gift.style.transform = `rotate(${rotFrom}deg)`;
            window.setTimeout(() => {
                if (gift.isConnected) {
                    gift.remove();
                }
            }, 2500);
        } else {
            gift.addEventListener('animationend', () => gift.remove());
        }

        this.playfield.appendChild(gift);
    }

    playCatchSound() {
        if (!this.catchSoundUrlValue) {
            return;
        }

        try {
            if (!this.catchSound) {
                this.catchSound = new Audio(this.catchSoundUrlValue);
                this.catchSound.volume = 0.55;
            }
            const nod = this.catchSound.cloneNode();
            nod.volume = 0.55;
            nod.play().catch(() => {});
        } catch {
            // ignore playback errors
        }
    }

    spawnCatchEffect(x, y) {
        if (!this.playfield) {
            return;
        }

        const burst = document.createElement('div');
        burst.className = 'elf-catch-burst';
        burst.style.left = `${x}px`;
        burst.style.top = `${y}px`;
        burst.innerHTML = `
            <span class="elf-catch-plus">+1</span>
            <span class="elf-catch-spark" style="--elf-spark-angle: -70deg"></span>
            <span class="elf-catch-spark" style="--elf-spark-angle: -20deg"></span>
            <span class="elf-catch-spark" style="--elf-spark-angle: 25deg"></span>
            <span class="elf-catch-spark" style="--elf-spark-angle: 75deg"></span>
            <span class="elf-catch-spark" style="--elf-spark-angle: 140deg"></span>
            <span class="elf-catch-spark" style="--elf-spark-angle: 200deg"></span>
        `;
        this.playfield.appendChild(burst);
        window.setTimeout(() => burst.remove(), 650);
    }

    startMusic() {
        this.stopMusic();
        if (!this.musicUrlValue) {
            return;
        }

        const audio = new Audio(this.musicUrlValue);
        audio.loop = true;
        audio.volume = 0.45;
        this.audio = audio;
        audio.play().catch(() => {
            // Autoplay may be blocked outside the click gesture in some browsers;
            // the elf click usually counts as a user gesture.
        });
    }

    stopMusic() {
        if (!this.audio) {
            return;
        }
        this.audio.pause();
        this.audio.currentTime = 0;
        this.audio = null;
    }

    endGame() {
        if (!this.playing) {
            return;
        }

        const isNewBest = this.score > this.bestScore;
        if (isNewBest) {
            this.bestScore = this.score;
            this.writeBestScore(this.bestScore);
        }

        this.stopLoops();
        this.stopMusic();
        this.stopSnow();
        this.playing = false;

        if (!this.overlay) {
            return;
        }

        if (this.playfield) {
            this.playfield.replaceChildren();
        }

        const result = this.overlay.querySelector('[data-elf-result]');
        if (result) {
            result.hidden = false;
            result.innerHTML = `
                <p class="elf-result-score">Score : <strong>${this.score}</strong></p>
                <p class="elf-result-best">Meilleur score : <strong>${this.bestScore}</strong>${isNewBest ? ' · nouveau record !' : ''}</p>
                <p class="elf-result-save" data-elf-save role="status">${this.score > 0 ? 'Enregistrement du score…' : ''}</p>
                <p class="elf-result-joke">Ton Santa a été notifié… non, on plaisante.</p>
            `;
        }

        // Always submit (even 0-ish / non-record) to burn the one-shot server session.
        if (this.score > 0) {
            this.persistBestScore(this.score);
        } else {
            this.sessionToken = null;
        }
    }

    setSaveStatus(message, isError = false) {
        const el = this.overlay?.querySelector('[data-elf-save]');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.toggle('is-error', isError);
    }

    persistBestScore(score) {
        if (!this.scoreUrlValue || !this.csrfTokenValue || !this.sessionToken) {
            this.setSaveStatus('Score non envoyé (session manquante).', true);
            return;
        }

        const sessionToken = this.sessionToken;
        this.sessionToken = null;

        fetch(this.scoreUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrfTokenValue,
            },
            body: JSON.stringify({ score, sessionToken }),
            credentials: 'same-origin',
            keepalive: true,
        })
            .then(async (response) => {
                let data = null;
                try {
                    data = await response.json();
                } catch {
                    data = null;
                }
                return { response, data };
            })
            .then(({ response, data }) => {
                if (data && typeof data.bestScore === 'number') {
                    this.bestScore = Math.max(this.bestScore, data.bestScore);
                    this.writeBestScore(this.bestScore);
                    this.updateHud();
                    const bestLabel = this.overlay?.querySelector('.elf-result-best strong');
                    if (bestLabel) {
                        bestLabel.textContent = String(this.bestScore);
                    }
                    this.setSaveStatus(
                        data.updated ? 'Score enregistré au classement !' : 'Score reçu (pas de nouveau record).',
                    );
                    return;
                }

                const error = data?.error ?? (response.ok ? 'unknown' : `http_${response.status}`);
                this.setSaveStatus(this.saveErrorMessage(error), true);
            })
            .catch(() => {
                this.setSaveStatus('Impossible d’enregistrer le score (réseau).', true);
            });
    }

    saveErrorMessage(error) {
        switch (error) {
            case 'too_early':
                return 'Partie trop courte pour le serveur. Rejoue jusqu’à la fin.';
            case 'expired':
                return 'Session expirée (onglet en veille trop longtemps). Rejoue une partie.';
            case 'csrf':
            case 'session':
            case 'no_session':
                return 'Session invalide. Recharge la page puis rejoue.';
            case 'disabled':
                return 'Le jeu est désactivé.';
            default:
                return 'Score non enregistré. Réessaie.';
        }
    }

    renderOverlay({ loading = false } = {}) {
        this.destroyOverlay();

        const minutes = Math.floor(this.secondsLeft / 60);
        const seconds = this.secondsLeft % 60;
        const timeLabel = `${minutes}:${String(seconds).padStart(2, '0')}`;

        const overlay = document.createElement('div');
        overlay.className = `elf-overlay${loading ? ' is-loading' : ''}`;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Le lutin farceur');
        overlay.innerHTML = `
            <button type="button" class="elf-close" data-elf-close aria-label="Quitter le jeu">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="elf-hud" data-elf-hud>
                <span data-elf-score>Score : 0</span>
                <span data-elf-time>${timeLabel}</span>
                <span data-elf-best>Record : ${this.bestScore}</span>
            </div>
            <div class="elf-snow" data-elf-snow aria-hidden="true"></div>
            <div class="elf-playfield" data-elf-playfield></div>
            <div class="elf-result" data-elf-result hidden></div>
            ${loading ? `
                <div class="elf-loading" data-elf-loading role="status" aria-live="polite">
                    <span class="elf-loading-spinner" aria-hidden="true"></span>
                    <p>Préparation de la partie…</p>
                </div>
            ` : ''}
        `;

        overlay.querySelector('[data-elf-close]')?.addEventListener('click', () => this.closeOverlay());

        document.body.appendChild(overlay);
        document.body.classList.add('elf-game-open');
        this.overlay = overlay;
        this.playfield = overlay.querySelector('[data-elf-playfield]');
        this.snowLayer = overlay.querySelector('[data-elf-snow]');
        this.onKeydown = (event) => {
            if (event.key === 'Escape') {
                this.closeOverlay();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
    }

    startSnow() {
        this.stopSnow();
        if (!this.snowLayer) {
            return;
        }

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const initial = reduceMotion ? 12 : 28;
        for (let i = 0; i < initial; i += 1) {
            this.spawnSnowflake(true);
        }

        if (reduceMotion) {
            return;
        }

        this.snowTimer = window.setInterval(() => {
            if (!this.playing || !this.snowLayer) {
                return;
            }
            this.spawnSnowflake(false);
        }, 280);
    }

    spawnSnowflake(scatter) {
        if (!this.snowLayer) {
            return;
        }

        const flake = document.createElement('span');
        flake.className = 'elf-snowflake';
        const size = 0.35 + Math.random() * 0.85;
        const duration = 6 + Math.random() * 8;
        const drift = Math.round(-40 + Math.random() * 80);
        flake.style.left = `${Math.random() * 100}%`;
        flake.style.setProperty('--elf-snow-size', `${size}rem`);
        flake.style.setProperty('--elf-snow-duration', `${duration}s`);
        flake.style.setProperty('--elf-snow-drift', `${drift}px`);
        flake.style.setProperty('--elf-snow-opacity', `${0.35 + Math.random() * 0.55}`);
        flake.style.animationDelay = scatter ? `-${Math.random() * duration}s` : '0s';
        flake.innerHTML = '<i class="fa-solid fa-snowflake" aria-hidden="true"></i>';
        flake.addEventListener('animationend', () => flake.remove());
        this.snowLayer.appendChild(flake);
    }

    stopSnow() {
        window.clearInterval(this.snowTimer);
        this.snowTimer = null;
        if (this.snowLayer) {
            this.snowLayer.replaceChildren();
        }
    }

    updateHud() {
        if (!this.overlay) {
            return;
        }
        const score = this.overlay.querySelector('[data-elf-score]');
        const time = this.overlay.querySelector('[data-elf-time]');
        const best = this.overlay.querySelector('[data-elf-best]');
        if (score) {
            score.textContent = `Score : ${this.score}`;
        }
        if (time) {
            const minutes = Math.floor(this.secondsLeft / 60);
            const seconds = this.secondsLeft % 60;
            time.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
        }
        if (best) {
            best.textContent = `Record : ${this.bestScore}`;
        }
    }

    destroyOverlay() {
        if (this.onKeydown) {
            document.removeEventListener('keydown', this.onKeydown);
            this.onKeydown = null;
        }
        if (this.overlay) {
            this.overlay.remove();
            this.overlay = null;
        }
        this.playfield = null;
        this.snowLayer = null;
        document.body.classList.remove('elf-game-open');
    }

    closeOverlay() {
        const wasStarting = this.starting;
        this.startAbort?.abort();
        this.startAbort = null;
        this.starting = false;
        this.stopLoops();
        this.stopMusic();
        this.stopSnow();
        this.playing = false;
        this.sessionToken = null;
        this.startedAtMs = null;
        this.destroyOverlay();
        this.removeHiddenElf();
        if (wasStarting) {
            this.placeHiddenElf();
        }
    }

    stopLoops() {
        window.clearTimeout(this.spawnTimer);
        window.clearInterval(this.countdownTimer);
        this.burstTimers.forEach((timer) => window.clearTimeout(timer));
        this.burstTimers = [];
        this.spawnTimer = null;
        this.countdownTimer = null;
    }

    teardown() {
        this.closeOverlay();
    }

    readBestScore() {
        try {
            const raw = window.localStorage.getItem(this.storageKeyValue);
            const value = Number.parseInt(raw ?? '0', 10);
            return Number.isFinite(value) && value > 0 ? value : 0;
        } catch {
            return 0;
        }
    }

    writeBestScore(score) {
        try {
            window.localStorage.setItem(this.storageKeyValue, String(score));
        } catch {
            // ignore quota / private mode
        }
    }
}
