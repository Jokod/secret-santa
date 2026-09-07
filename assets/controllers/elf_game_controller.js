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
    };

    connect() {
        this.playing = false;
        this.score = 0;
        this.elapsed = 0;
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
        this.bestScore = this.readBestScore();
        this.placeHiddenElf();
    }

    disconnect() {
        this.teardown();
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

        const peeks = ['elf-peek-top', 'elf-peek-right', 'elf-peek-bottom', 'elf-peek-left'];
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

        elf.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.startGame();
        });

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

    startGame() {
        if (this.playing) {
            return;
        }

        this.removeHiddenElf();
        this.playing = true;
        this.score = 0;
        this.elapsed = 0;
        this.secondsLeft = this.durationValue;
        this.renderOverlay();
        this.updateHud();
        this.startMusic();
        this.startSnow();
        this.countdownTimer = window.setInterval(() => this.tick(), 1000);
        this.scheduleSpawn();
    }

    tick() {
        if (!this.playing) {
            return;
        }

        this.secondsLeft -= 1;
        this.elapsed += 1;
        this.updateHud();

        if (this.secondsLeft <= 0) {
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
                <p class="elf-result-joke">Ton Santa a été notifié… non, on plaisante.</p>
            `;
        }
    }

    renderOverlay() {
        this.destroyOverlay();

        const overlay = document.createElement('div');
        overlay.className = 'elf-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Le lutin farceur');
        overlay.innerHTML = `
            <button type="button" class="elf-close" data-elf-close aria-label="Quitter le jeu">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="elf-hud" data-elf-hud>
                <span data-elf-score>Score : 0</span>
                <span data-elf-time>1:00</span>
                <span data-elf-best>Record : ${this.bestScore}</span>
            </div>
            <div class="elf-snow" data-elf-snow aria-hidden="true"></div>
            <div class="elf-playfield" data-elf-playfield></div>
            <div class="elf-result" data-elf-result hidden></div>
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
        this.stopLoops();
        this.stopMusic();
        this.stopSnow();
        this.playing = false;
        this.destroyOverlay();
        this.removeHiddenElf();
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
