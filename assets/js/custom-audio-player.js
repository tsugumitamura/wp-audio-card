(() => {
  // mm:ss 形式
  const fmt = (sec) => {
    if (!isFinite(sec) || sec < 0) return "00:00";
    const s = Math.floor(sec % 60).toString().padStart(2, "0");
    const m = Math.floor(sec / 60).toString().padStart(2, "0");
    return `${m}:${s}`;
  };

  // 総時間をUIに反映（取得できたら true）
  const applyDurationUI = (audio, durEl) => {
    if (isFinite(audio.duration) && audio.duration > 0) {
      durEl.textContent = fmt(audio.duration);
      return true;
    }
    return false;
  };

  // 最終手段：末尾へダミーシーク → duration を確定させる（再生はしない）
  const forceResolveDuration = (audio, durEl, onDone) => {
    if (applyDurationUI(audio, durEl)) { onDone?.(); return; }
    if (audio.readyState < 1) { onDone?.(); return; } // メタデータ未取得

    const prevTime = audio.currentTime;
    const onSeeked = () => {
      applyDurationUI(audio, durEl);          // seeked後は大抵 duration が入る
      try { audio.currentTime = prevTime || 0; } catch(_) {}
      audio.removeEventListener("seeked", onSeeked);
      onDone?.();
    };
    audio.addEventListener("seeked", onSeeked, { once: true });
    try { audio.currentTime = 1e7; } catch(_) {
      audio.removeEventListener("seeked", onSeeked);
      onDone?.();
    }
  };

  // Safari等対策：イベント＋短時間ポーリング＋ダミーシーク
  const ensureDuration = (audio, durEl, onReady) => {
    // 1) 即時チェック
    if (applyDurationUI(audio, durEl)) {
      onReady?.();
      return;
    }

    // 2) イベントで拾う
    const tryApply = () => {
      if (applyDurationUI(audio, durEl)) {
        detach();
        onReady?.();
        return true;
      }
      return false;
    };
    const evs = ["loadedmetadata", "durationchange", "loadeddata", "canplay", "canplaythrough"];
    const handlers = evs.map(ev => {
      const h = () => { tryApply(); };
      audio.addEventListener(ev, h);
      return [ev, h];
    });
    const detach = () => handlers.forEach(([ev,h]) => audio.removeEventListener(ev, h));

    // 3) ポーリング（最大4秒）
    const started = Date.now();
    const tid = setInterval(() => {
      if (tryApply() || Date.now() - started > 4000) {
        clearInterval(tid);
        detach();
        if (!applyDurationUI(audio, durEl)) {
          // 4) まだダメなら、ダミーシークで確定
          forceResolveDuration(audio, durEl, onReady);
        } else {
          onReady?.();
        }
      }
    }, 120);

    // preload="none" ならメタデータ取得を促す
    try { if (audio.preload === "none") audio.load(); } catch(_) {}
  };

  // 5段階ボリューム（0/25/50/75/100%）
  const VLEVELS = [0, 0.25, 0.5, 0.75, 1];

  const initPlayer = (root) => {
    const audio    = root.querySelector(".cap__audio");
    const btnPlay  = root.querySelector(".cap__play");
    const seek     = root.querySelector(".cap__seek");
    const cur      = root.querySelector(".cap__current");
    const dur      = root.querySelector(".cap__duration");
    const muteBtn  = root.querySelector(".cap__mute");
    const volBars  = root.querySelectorAll(".cap__volbar");
    const volList  = root.querySelector(".cap__volbars");

    // 初期値
    audio.volume = 0.8;
    let isDragging = false;

    // 進捗%をCSS変数へ反映（Chrome/Safariの伸び描画用）
    const setSeekBG = () => {
      const p = (audio.currentTime / (audio.duration || 1)) * 100 || 0;
      seek.style.setProperty("--value", `${p}%`);
    };

    // 再生状態UI
    const updatePlayState = () => {
      const playing = !audio.paused && !audio.ended;
      btnPlay.setAttribute("aria-pressed", playing ? "true" : "false");
      btnPlay.setAttribute("aria-label", playing ? "Pause" : "Play");
      btnPlay.classList.toggle("is-playing", playing);
    };

    // 再生/一時停止
    btnPlay.addEventListener("click", () => {
      if (audio.paused) audio.play(); else audio.pause();
    });
    audio.addEventListener("play",  updatePlayState);
    audio.addEventListener("pause", updatePlayState);
    audio.addEventListener("ended", updatePlayState);

    // 総時間の確定（ページロード直後）
    ensureDuration(audio, dur, () => {
      // 初期表示で一度だけ背景反映
      setSeekBG();
    });
    // 念のため少し遅らせてもう一度
    setTimeout(() => ensureDuration(audio, dur, setSeekBG), 300);

    // シーク最大値（%で扱う）
    seek.max = 100;

    // 時間・シーク（再生中の更新）
    audio.addEventListener("timeupdate", () => {
      if (isDragging) return;
      const p = (audio.currentTime / (audio.duration || 1)) * 100 || 0;
      seek.value = p;
      cur.textContent = fmt(audio.currentTime);
      // 進捗背景の更新（Chrome/Safari）
      seek.style.setProperty("--value", `${p}%`);
    });

    // ドラッグ中プレビュー（UIのみ更新）
    seek.addEventListener("input", () => {
      isDragging = true;
      const t = (seek.value / 100) * (audio.duration || 0);
      cur.textContent = fmt(t);
      // ドラッグ中も背景を更新
      seek.style.setProperty("--value", `${seek.value}%`);
    });

    // ドラッグ確定でシーク
    seek.addEventListener("change", () => {
      const t = (seek.value / 100) * (audio.duration || 0);
      audio.currentTime = t;
      isDragging = false;
      // 念のため背景も確定値で更新
      seek.style.setProperty("--value", `${seek.value}%`);
    });

    // ミュート
    const applyMuteUI = () => {
      muteBtn.setAttribute("aria-pressed", audio.muted ? "true" : "false");
      muteBtn.setAttribute("aria-label", audio.muted ? "Unmute" : "Mute");
      root.classList.toggle("is-muted", audio.muted);
    };
    muteBtn.addEventListener("click", () => {
      audio.muted = !audio.muted;
      applyMuteUI();
    });

    // 5段階ボリューム制御
    const setVolumeByIndex = (idx) => {
      const clamped = Math.max(0, Math.min(4, idx));
      audio.volume = VLEVELS[clamped];
      audio.muted = (clamped === 0);
      applyMuteUI();

      volBars.forEach((el, i) => el.classList.toggle("is-on", i <= clamped - 1));

      const percent = Math.round(audio.volume * 100);
      volList.setAttribute("aria-valuenow", String(percent));
      volList.setAttribute("aria-valuetext", `${percent}% volume`);
    };

    volBars.forEach((bar, i) => {
      bar.addEventListener("click", () => setVolumeByIndex(i + 1));
      bar.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          setVolumeByIndex(i + 1);
        }
      });
      bar.setAttribute("tabindex", "0");
      bar.setAttribute("role", "button");
      bar.setAttribute("aria-label", "Set volume");
    });

    // キーボード左右で音量調整
    volList.addEventListener("keydown", (e) => {
      const curIdx = VLEVELS.findIndex(v => v >= audio.volume - 0.001);
      if (e.key === "ArrowRight") setVolumeByIndex(Math.min(4, curIdx + 1));
      if (e.key === "ArrowLeft")  setVolumeByIndex(Math.max(0, curIdx - 1));
    });

    // 初期UI
    setVolumeByIndex(4);
    applyMuteUI();
    updatePlayState();
  };

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".js-audio-player").forEach(initPlayer);
  });
})();
