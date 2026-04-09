document.addEventListener("DOMContentLoaded", () => {
    bootStorefrontExperience();
});

function bootStorefrontExperience() {
    initGlobalUiOnce();
    bootCurrentPageUi();
}

function initGlobalUiOnce() {
    if (window.__globalUiInitialized === true) {
        return;
    }

    window.__globalUiInitialized = true;

    initSplash();
    mountFloatingCartLayer();
    initFloatingCartViewportSync();
    initUtilityMenu();
    initNotificationMenu();
    initNotificationsModal();
    initLiveCustomerNotifications();
    initGoogleIdentityButtons();
    initProductFavorites();
    initCopyButtons();
    initStorefrontInstantNavigation();
}

function bootCurrentPageUi() {
    initShowcaseCarousel();
    initCategoryObserver();
    initCatalogSearch();
    initPasswordVisibilityToggles();
    initProductDetailsToggles();
    initProductQuantitySteppers();
    initProductFlavorOrdering();
    initProductCartAjax();
    initProductCardNavigation();
    initProductGallery();
    initProductShare();
    initCartAjax();
    initCartQuantitySteppers();
    initCheckoutOptionsForm();
    initTrackingAutoRefresh();
    initSignupForm();
    initPhoneMasks();
    initAddressForms();
}

function mountFloatingCartLayer() {
    const trigger = document.querySelector(".floating-cart");
    const popover = document.querySelector(".floating-cart-popover");
    const overlay = document.querySelector(".cart-overlay");

    if (trigger instanceof HTMLElement && trigger.closest(".storefront-utility")) {
        return;
    }

    let layer = document.querySelector(".floating-cart-layer");

    if (!(layer instanceof HTMLElement)) {
        layer = document.createElement("div");
        layer.className = "floating-cart-layer";
        document.body.appendChild(layer);
    }

    [trigger, popover].forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        if (node.parentElement !== layer) {
            layer.appendChild(node);
        }
    });

    if (overlay instanceof HTMLElement && overlay.parentElement !== document.body) {
        document.body.appendChild(overlay);
    }
}

function initFloatingCartViewportSync() {
    const cart = document.querySelector(".floating-cart");
    const popover = document.querySelector(".floating-cart-popover");
    const utilityToggle = document.querySelector("[data-utility-toggle]");

    if (!(cart instanceof HTMLElement)) {
        return;
    }

    if (cart.closest(".storefront-utility")) {
        cart.style.left = "";
        cart.style.top = "";
        cart.style.right = "";
        cart.style.bottom = "";

        if (popover instanceof HTMLElement) {
            popover.style.left = "";
            popover.style.top = "";
            popover.style.right = "";
            popover.style.bottom = "";
        }

        return;
    }

    let rafId = 0;

    const sync = () => {
        rafId = 0;

        if (!window.matchMedia("(max-width: 767px)").matches) {
            cart.style.left = "";
            cart.style.top = "";
            cart.style.right = "";
            cart.style.bottom = "";

            if (popover instanceof HTMLElement) {
                popover.style.left = "";
                popover.style.top = "";
                popover.style.right = "";
                popover.style.bottom = "";
            }

            return;
        }

        const viewport = window.visualViewport;
        const layoutWidth = Math.round(window.innerWidth);
        const layoutHeight = Math.round(window.innerHeight);
        const viewportWidth = Math.round(viewport?.width ?? layoutWidth);
        const viewportHeight = Math.round(viewport?.height ?? layoutHeight);
        const offsetLeft = Math.round(viewport?.offsetLeft ?? 0);
        const offsetTop = Math.round(viewport?.offsetTop ?? 0);
        const toggleRect = utilityToggle instanceof HTMLElement ? utilityToggle.getBoundingClientRect() : null;
        const cartLeft = Math.max(12, Math.round(offsetLeft + (toggleRect?.left ?? 18)));
        const cartTop = Math.max(12, Math.round(offsetTop + (toggleRect?.bottom ?? 62) + 10));

        cart.style.left = `${cartLeft}px`;
        cart.style.top = `${cartTop}px`;
        cart.style.right = "auto";
        cart.style.bottom = "auto";

        if (popover instanceof HTMLElement) {
            const cartRect = cart.getBoundingClientRect();
            const popoverWidth = Math.min(240, layoutWidth - 24);
            const preferredLeft = Math.round(offsetLeft + cartRect.right + 10);
            const popoverLeft = Math.max(12, Math.min(layoutWidth - popoverWidth - 12, preferredLeft));
            const popoverTop = Math.max(12, Math.round(offsetTop + cartRect.top + 2));

            popover.style.left = `${popoverLeft}px`;
            popover.style.top = `${popoverTop}px`;
            popover.style.right = "auto";
            popover.style.bottom = "auto";
        }
    };

    const queueSync = () => {
        if (rafId) {
            window.cancelAnimationFrame(rafId);
        }

        rafId = window.requestAnimationFrame(sync);
    };

    window.__syncFloatingCartViewportPosition = queueSync;

    queueSync();
    window.addEventListener("resize", queueSync, { passive: true });
    window.addEventListener("scroll", queueSync, { passive: true });
    window.visualViewport?.addEventListener("resize", queueSync);
    window.visualViewport?.addEventListener("scroll", queueSync);
}

function initSplash() {
    const splash = document.querySelector("[data-splash]");

    window.setTimeout(() => {
        if (splash) {
            splash.classList.add("is-hidden");
        }
    }, 750);
}

function initPasswordVisibilityToggles() {
    const toggles = document.querySelectorAll("[data-password-toggle]");

    toggles.forEach((toggle) => {
        if (!(toggle instanceof HTMLButtonElement)) {
            return;
        }

        const targetId = toggle.getAttribute("data-password-target");

        if (!targetId) {
            return;
        }

        const input = document.getElementById(targetId);

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        toggle.addEventListener("click", () => {
            const shouldReveal = input.type === "password";
            input.type = shouldReveal ? "text" : "password";
            toggle.classList.toggle("is-active", shouldReveal);
            toggle.setAttribute("aria-pressed", shouldReveal ? "true" : "false");
            toggle.setAttribute("aria-label", shouldReveal ? "Ocultar senha" : "Mostrar senha");
        });
    });
}

function initGoogleIdentityButtons() {
    const buttonContainer = document.querySelector("[data-google-auth-button]");
    const buttonHost = document.querySelector("[data-google-auth-wrap]");

    if (!(buttonContainer instanceof HTMLElement)) {
        return;
    }

    const formId = buttonContainer.dataset.googleAuthForm || "";
    const authForm = document.getElementById(formId);
    const credentialInput = authForm?.querySelector("[data-google-credential-input]");
    const clientId = buttonContainer.dataset.googleClientId || "";
    const loginUri = buttonContainer.dataset.googleLoginUri || "";
    let googleInitialized = false;
    let renderAttempts = 0;

    const useRedirectMode = loginUri !== "";
    const canUsePopupMode = authForm instanceof HTMLFormElement && credentialInput instanceof HTMLInputElement;

    if (clientId === "" || (!useRedirectMode && !canUsePopupMode)) {
        return;
    }

    const canUseGoogle = () => Boolean(window.google && window.google.accounts && window.google.accounts.id);
    const isTouchDevice = () => window.matchMedia("(hover: none), (pointer: coarse)").matches || navigator.maxTouchPoints > 0;
    const triggerGooglePrompt = () => {
        if (!canUseGoogle()) {
            return;
        }

        try {
            window.google.accounts.id.prompt();
        } catch (error) {
            // Mantem o botao oficial como fluxo principal.
        }
    };

    const forwardTouchToGoogleButton = () => {
        const clickTarget = buttonContainer.querySelector('[role="button"], [tabindex], iframe, div');

        if (!(clickTarget instanceof HTMLElement)) {
            return false;
        }

        try {
            clickTarget.click();
            return true;
        } catch (error) {
            return false;
        }
    };

    const renderGoogleButton = () => {
        if (!canUseGoogle()) {
            return false;
        }

        if (!googleInitialized) {
            const googleConfig = {
                client_id: clientId,
            };

            if (useRedirectMode) {
                googleConfig.ux_mode = "redirect";
                googleConfig.login_uri = loginUri;
            } else {
                googleConfig.callback = (response) => {
                    if (!response || !response.credential || !(credentialInput instanceof HTMLInputElement) || !(authForm instanceof HTMLFormElement)) {
                        return;
                    }

                    credentialInput.value = response.credential;
                    authForm.submit();
                };
            }

            window.google.accounts.id.initialize(googleConfig);
            googleInitialized = true;
        }

        buttonContainer.innerHTML = "";

        window.google.accounts.id.renderButton(buttonContainer, {
            type: buttonContainer.dataset.googleButtonType || "icon",
            theme: "outline",
            shape: buttonContainer.dataset.googleButtonShape || "square",
            size: buttonContainer.dataset.googleButtonSize || "large",
        });

        return true;
    };

    const tryRenderGoogleButton = () => {
        renderAttempts += 1;

        if (renderGoogleButton() || renderAttempts >= 40) {
            return;
        }

        window.setTimeout(tryRenderGoogleButton, 250);
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", tryRenderGoogleButton, { once: true });
    } else {
        tryRenderGoogleButton();
    }

    if (!useRedirectMode && buttonHost instanceof HTMLElement) {
        buttonHost.addEventListener("click", (event) => {
            if (!(event.target instanceof HTMLElement)) {
                triggerGooglePrompt();
                return;
            }

            if (event.target.closest("[data-google-auth-button]")) {
                return;
            }

            triggerGooglePrompt();
        });

        buttonHost.addEventListener("keydown", (event) => {
            if (!(event instanceof KeyboardEvent)) {
                return;
            }

            if (event.key !== "Enter" && event.key !== " ") {
                return;
            }

            event.preventDefault();
            triggerGooglePrompt();
        });
    }

    if (useRedirectMode && buttonHost instanceof HTMLElement) {
        buttonHost.addEventListener("click", (event) => {
            if (!isTouchDevice()) {
                return;
            }

            if (event.target instanceof HTMLElement) {
                const googleButtonTarget = event.target.closest("[data-google-auth-button]");

                if (googleButtonTarget instanceof HTMLElement && googleButtonTarget !== buttonContainer) {
                    return;
                }
            }

            forwardTouchToGoogleButton();
        });
    }

    window.addEventListener("load", () => {
        window.setTimeout(tryRenderGoogleButton, 80);
        window.setTimeout(tryRenderGoogleButton, 320);
    });
}

function initUtilityMenu() {
    const menu = document.querySelector("[data-utility-menu]");
    const toggle = document.querySelector("[data-utility-toggle]");
    const panel = document.querySelector("[data-utility-panel]");
    const mainView = panel?.querySelector("[data-utility-main-view]");
    const notificationsView = panel?.querySelector("[data-utility-notifications-view]");
    const openNotificationsButton = panel?.querySelector("[data-utility-notifications-open]");
    const backNotificationsButton = panel?.querySelector("[data-utility-notifications-back]");

    if (!menu || !toggle || !panel) {
        return;
    }

    const showMainView = () => {
        if (mainView instanceof HTMLElement) {
            mainView.hidden = false;
        }

        if (notificationsView instanceof HTMLElement) {
            notificationsView.hidden = true;
        }
    };

    const showNotificationsView = () => {
        if (mainView instanceof HTMLElement) {
            mainView.hidden = true;
        }

        if (notificationsView instanceof HTMLElement) {
            notificationsView.hidden = false;
        }

        if (typeof window.__refreshCustomerNotifications === "function") {
            window.__refreshCustomerNotifications();
        }
    };

    const closeMenu = () => {
        menu.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
        showMainView();
    };

    const openMenu = () => {
        menu.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");
        showMainView();
    };

    toggle.addEventListener("click", () => {
        if (menu.classList.contains("is-open")) {
            closeMenu();
            return;
        }

        openMenu();
    });

    document.addEventListener("click", (event) => {
        if (menu.contains(event.target)) {
            return;
        }

        closeMenu();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeMenu();
        }
    });

    if (openNotificationsButton instanceof HTMLButtonElement) {
        openNotificationsButton.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            showNotificationsView();
        });
    }

    if (backNotificationsButton instanceof HTMLButtonElement) {
        backNotificationsButton.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            showMainView();
        });
    }
}

function initNotificationMenu() {
    const menu = document.querySelector("[data-notification-menu]");
    const toggle = document.querySelector("[data-notification-toggle]");
    const panel = document.querySelector("[data-notification-panel]");

    if (!menu || !toggle || !panel) {
        return;
    }

    const closeMenu = () => {
        menu.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
    };

    const openMenu = () => {
        document.querySelector("[data-utility-menu]")?.classList.remove("is-open");
        document.querySelector("[data-utility-toggle]")?.setAttribute("aria-expanded", "false");
        menu.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");
    };

    toggle.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (menu.classList.contains("is-open")) {
            closeMenu();
            return;
        }

        openMenu();
    });

    document.addEventListener("click", (event) => {
        if (menu.contains(event.target)) {
            return;
        }

        closeMenu();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeMenu();
        }
    });
}

function initLiveCustomerNotifications() {
    const utilityMenu = document.querySelector("[data-notifications-feed-url]");
    const notificationsBody = document.querySelector("[data-live-notifications]");

    if (!(utilityMenu instanceof HTMLElement) || !(notificationsBody instanceof HTMLElement)) {
        return;
    }

    const feedUrl = utilityMenu.dataset.notificationsFeedUrl || "";

    if (feedUrl === "") {
        return;
    }

    let requestInFlight = false;
    let lastHtml = notificationsBody.innerHTML.trim();
    let lastUnreadCount = null;

    const syncUnreadCount = (value) => {
        const unreadCount = Math.max(0, Number.parseInt(String(value || 0), 10) || 0);
        const buttons = utilityMenu.querySelectorAll("[data-utility-notifications-open], [data-utility-toggle]");

        buttons.forEach((button) => {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            const isUtilityToggle = button.hasAttribute("data-utility-toggle");
            let badge = button.querySelector(isUtilityToggle ? "[data-utility-menu-count]" : "[data-notification-count]");

            if (unreadCount <= 0) {
                badge?.remove();
                return;
            }

            if (!(badge instanceof HTMLElement)) {
                badge = document.createElement("span");
                badge.className = isUtilityToggle
                    ? "storefront-notification-menu__count storefront-utility-menu__count"
                    : "storefront-notification-menu__count";
                badge.setAttribute(isUtilityToggle ? "data-utility-menu-count" : "data-notification-count", "");
                button.appendChild(badge);
            }

            badge.textContent = String(unreadCount);
        });

        lastUnreadCount = unreadCount;
    };

    const applyPayload = (payload) => {
        if (!payload || payload.success !== true) {
            return;
        }

        if (typeof payload.html === "string") {
            const nextHtml = payload.html.trim();

            if (nextHtml !== "" && nextHtml !== lastHtml) {
                notificationsBody.innerHTML = nextHtml;
                lastHtml = nextHtml;
            }
        }

        if (lastUnreadCount !== payload.unread_count) {
            syncUnreadCount(payload.unread_count);
        }
    };

    const refreshNotifications = async () => {
        if (requestInFlight) {
            return;
        }

        requestInFlight = true;

        try {
            const response = await fetch(feedUrl, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                cache: "no-store",
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            applyPayload(payload);
        } catch (error) {
            // Mantem a ultima lista carregada sem quebrar a interface.
        } finally {
            requestInFlight = false;
        }
    };

    window.__refreshCustomerNotifications = refreshNotifications;

    syncUnreadCount(utilityMenu.querySelector("[data-notification-count]")?.textContent || 0);
    window.setTimeout(refreshNotifications, 800);
    window.setInterval(refreshNotifications, 3000);

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            refreshNotifications();
        }
    });
}

function initNotificationsModal() {
    const modal = document.querySelector("[data-notifications-modal]");
    const modalBody = modal?.querySelector("[data-notifications-modal-body]");
    const openButtons = document.querySelectorAll("[data-notifications-modal-open]");
    const closeButtons = modal?.querySelectorAll("[data-notifications-modal-close]") || [];

    if (!(modal instanceof HTMLElement) || !(modalBody instanceof HTMLElement) || !openButtons.length) {
        return;
    }

    const feedUrl = modalBody.dataset.notificationsModalFeedUrl || "";
    let requestInFlight = false;
    let lastFocusedElement = null;

    const closeModal = () => {
        modal.classList.remove("is-open");
        document.body.classList.remove("has-notifications-modal");

        window.setTimeout(() => {
            modal.hidden = true;
        }, 220);

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    };

    const refreshModalNotifications = async () => {
        if (feedUrl === "" || requestInFlight) {
            return;
        }

        requestInFlight = true;

        try {
            const response = await fetch(feedUrl, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                cache: "no-store",
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            if (!payload || payload.success !== true || typeof payload.html !== "string") {
                return;
            }

            modalBody.innerHTML = payload.html.trim();
        } catch (error) {
            // Mantem o conteudo atual do modal.
        } finally {
            requestInFlight = false;
        }
    };

    const openModal = async (event) => {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        document.querySelector("[data-utility-menu]")?.classList.remove("is-open");
        document.querySelector("[data-utility-toggle]")?.setAttribute("aria-expanded", "false");
        document.querySelector("[data-notification-menu]")?.classList.remove("is-open");
        document.querySelector("[data-notification-toggle]")?.setAttribute("aria-expanded", "false");

        modal.hidden = false;
        document.body.classList.add("has-notifications-modal");

        window.requestAnimationFrame(() => {
            modal.classList.add("is-open");
        });

        await refreshModalNotifications();
    };

    openButtons.forEach((button) => {
        button.addEventListener("click", openModal);
    });

    closeButtons.forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            closeModal();
        });
    });

    modal.addEventListener("click", (event) => {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        if (event.target.hasAttribute("data-notifications-modal-close")) {
            closeModal();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("is-open")) {
            closeModal();
        }
    });
}

function initShowcaseCarousel() {
    const carousel = document.querySelector("[data-showcase-carousel]");
    const track = document.querySelector("[data-showcase-track]");
    const isMobileViewport = window.matchMedia("(max-width: 767px)").matches;

    if (!carousel || !track) {
        return;
    }

    const originalSlides = Array.from(track.querySelectorAll("[data-showcase-slide]"));

    if (originalSlides.length <= 1) {
        return;
    }

    if (isMobileViewport) {
        carousel.classList.add("is-native-scroll");
        track.style.transform = "none";
        track.style.transition = "none";
        return;
    }

    carousel.addEventListener("dragstart", (event) => {
        event.preventDefault();
    });

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let autoplayId = 0;
    let isPointerDown = false;
    let isDragging = false;
    let pointerId = null;
    let pointerStartX = 0;
    let pointerStartY = 0;
    let dragLastX = 0;
    let suppressClick = false;
    let currentOffset = 0;
    let cycleWidth = 0;
    const dragThreshold = 10;
    const horizontalDragBias = 1.1;

    const makeClone = (slide) => {
        const clone = slide.cloneNode(true);
        clone.dataset.showcaseClone = "true";
        clone.setAttribute("aria-hidden", "true");
        clone.tabIndex = -1;
        return clone;
    };

    const leadingClones = originalSlides.map(makeClone);
    const trailingClones = originalSlides.map(makeClone);

    leadingClones.reverse().forEach((clone) => track.insertBefore(clone, track.firstChild));
    trailingClones.forEach((clone) => track.appendChild(clone));

    const getStep = () => {
        const firstRealSlide = track.querySelector("[data-showcase-slide]");

        if (!firstRealSlide) {
            return 0;
        }

        const gap = parseFloat(window.getComputedStyle(track).gap || "0");
        return firstRealSlide.getBoundingClientRect().width + gap;
    };

    const normalizeOffset = () => {
        if (!cycleWidth) {
            return;
        }

        while (currentOffset < cycleWidth * 0.5) {
            currentOffset += cycleWidth;
        }

        while (currentOffset >= cycleWidth * 1.5) {
            currentOffset -= cycleWidth;
        }
    };

    const updatePosition = (animate = true) => {
        const step = getStep();

        if (!step) {
            return;
        }

        cycleWidth = step * originalSlides.length;
        normalizeOffset();
        track.style.transition = animate
            ? "transform 850ms cubic-bezier(0.22, 1, 0.36, 1)"
            : "none";
        track.style.transform = `translate3d(${-currentOffset}px, 0, 0)`;
    };

    const stopAutoplay = () => {
        if (autoplayId) {
            window.clearInterval(autoplayId);
            autoplayId = 0;
        }
    };

    const nextSlide = () => {
        const step = getStep();

        if (!step) {
            return;
        }

        currentOffset += step;
        updatePosition(true);
    };

    const prevSlide = () => {
        const step = getStep();

        if (!step) {
            return;
        }

        currentOffset -= step;
        updatePosition(true);
    };

    const startAutoplay = () => {
        if (reduceMotion) {
            return;
        }

        stopAutoplay();
        autoplayId = window.setInterval(nextSlide, 3600);
    };

    const maybeStartAutoplay = () => {
        if (reduceMotion || document.hidden || carousel.matches(":hover") || carousel.contains(document.activeElement)) {
            return;
        }

        startAutoplay();
    };

    track.addEventListener("transitionend", () => {
        updatePosition(false);
    });

    const handlePointerDown = (event) => {
        if (event.pointerType === "mouse" && event.button !== 0) {
            return;
        }

        stopAutoplay();
        isPointerDown = true;
        isDragging = false;
        pointerId = event.pointerId;
        pointerStartX = event.clientX;
        pointerStartY = event.clientY;
        dragLastX = event.clientX;
        suppressClick = false;
    };

    const handlePointerMove = (event) => {
        if (!isPointerDown || event.pointerId !== pointerId) {
            return;
        }

        const totalDeltaX = event.clientX - pointerStartX;
        const totalDeltaY = event.clientY - pointerStartY;
        const absDeltaX = Math.abs(totalDeltaX);
        const absDeltaY = Math.abs(totalDeltaY);

        if (!isDragging) {
            const movedEnough = absDeltaX >= dragThreshold;
            const mostlyHorizontal = absDeltaX > absDeltaY * horizontalDragBias;

            if (!movedEnough) {
                return;
            }

            if (!mostlyHorizontal) {
                return;
            }

            isDragging = true;
            suppressClick = true;
            stopAutoplay();
            track.style.transition = "none";
            carousel.classList.add("is-dragging");

            if (carousel.setPointerCapture) {
                carousel.setPointerCapture(pointerId);
            }
        }

        event.preventDefault();

        const deltaX = event.clientX - dragLastX;
        dragLastX = event.clientX;
        currentOffset -= deltaX;
        normalizeOffset();
        track.style.transform = `translate3d(${-currentOffset}px, 0, 0)`;
    };

    const releasePointerState = () => {
        isPointerDown = false;
        isDragging = false;
        pointerId = null;
    };

    const handlePointerUp = (event) => {
        if (!isPointerDown || event.pointerId !== pointerId) {
            return;
        }

        if (carousel.releasePointerCapture) {
            try {
                carousel.releasePointerCapture(pointerId);
            } catch (error) {
                // Ignore release errors when capture was not active.
            }
        }

        if (isDragging) {
            carousel.classList.remove("is-dragging");

            const step = getStep();

            if (step) {
                currentOffset = Math.round(currentOffset / step) * step;
            }

            updatePosition(true);
            window.setTimeout(() => {
                suppressClick = false;
            }, 140);
        }

        releasePointerState();
        maybeStartAutoplay();
    };

    carousel.addEventListener("pointerdown", handlePointerDown);
    carousel.addEventListener("pointermove", handlePointerMove);
    carousel.addEventListener("pointerup", handlePointerUp);
    carousel.addEventListener("pointercancel", handlePointerUp);
    carousel.addEventListener("lostpointercapture", handlePointerUp);
    carousel.addEventListener("click", (event) => {
        if (!suppressClick) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    }, true);

    carousel.addEventListener("mouseenter", stopAutoplay);
    carousel.addEventListener("mouseleave", maybeStartAutoplay);
    carousel.addEventListener("focusin", stopAutoplay);
    carousel.addEventListener("focusout", () => {
        window.setTimeout(maybeStartAutoplay, 0);
    });

    window.addEventListener("resize", () => {
        updatePosition(false);
    });

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoplay();
            return;
        }

        maybeStartAutoplay();
    });

    currentOffset = getStep() * originalSlides.length;
    updatePosition(false);
    maybeStartAutoplay();
}

function initCategoryObserver() {
    const categoryLinks = document.querySelectorAll("[data-category-link]");
    const sections = document.querySelectorAll("[data-category-section]");

    if (!categoryLinks.length || !sections.length) {
        return;
    }

    const activateLink = (id) => {
        categoryLinks.forEach((link) => {
            const target = link.getAttribute("href") === `#${id}`;
            link.classList.toggle("is-current", target);
        });
    };

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activateLink(entry.target.id);
                }
            });
        },
        {
            rootMargin: "-25% 0px -55% 0px",
            threshold: 0.1,
        }
    );

    sections.forEach((section) => observer.observe(section));
}

function initCatalogSearch() {
    const searchInput = document.querySelector("[data-catalog-search]");
    const searchForm = document.querySelector("[data-catalog-search-form]");
    const searchMode = searchForm?.dataset.catalogSearchMode || "global";
    const cards = Array.from(document.querySelectorAll("[data-product-card]"));
    const sections = Array.from(document.querySelectorAll("[data-search-section]"));
    const feedback = document.querySelector("[data-search-feedback]");
    const emptyState = document.querySelector("[data-search-empty]");
    const catalogAnchor = document.querySelector("#catalogo");

    if (!searchInput || searchMode !== "local" || cards.length === 0 || sections.length === 0) {
        return;
    }

    if (typeof searchForm.__catalogSearchCleanup === "function") {
        searchForm.__catalogSearchCleanup();
    }

    const aliasGroups = [
        ["whisky", "whiskey", "uisque"],
        ["vodka", "vodca"],
        ["energetico", "energy"],
        ["refri", "refrigerante"],
    ];

    const normalizeText = (value) => {
        let text = String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, " ")
            .trim();

        aliasGroups.forEach((group) => {
            const canonical = group[0];

            group.forEach((alias) => {
                text = text.replace(new RegExp(`\\b${alias}\\b`, "g"), canonical);
            });
        });

        return text.replace(/\s+/g, " ").trim();
    };

    const buildTermGroups = (value) => {
        const normalized = normalizeText(value);

        if (!normalized) {
            return [];
        }

        return normalized.split(" ").filter(Boolean).map((token) => {
            const matchingGroup = aliasGroups.find((group) => group.includes(token));
            const variants = matchingGroup || [token];
            return [...new Set(variants.map((item) => normalizeText(item)).filter(Boolean))];
        });
    };

    const buildPrefixes = (term) => {
        if (!term || term.length < 5) {
            return [];
        }

        const prefixes = [];
        const maxLength = Math.min(term.length - 1, 6);

        for (let size = maxLength; size >= 4; size -= 1) {
            prefixes.push(term.slice(0, size));
        }

        return [...new Set(prefixes)];
    };

    const textHasWordPrefix = (text, prefix) => {
        if (!text || !prefix) {
            return false;
        }

        return new RegExp(`\\b${prefix}`).test(text);
    };

    const cardMatchesTermGroups = (indexText, termGroups, allowPrefixFallback = false) => (
        termGroups.every((variants) => {
            const exactMatch = variants.some((variant) => variant && indexText.includes(variant));

            if (exactMatch) {
                return true;
            }

            if (!allowPrefixFallback) {
                return false;
            }

            return variants.some((variant) => buildPrefixes(variant).some((prefix) => textHasWordPrefix(indexText, prefix)));
        })
    );

    const defaultFeedback = feedback?.textContent?.trim() || `${cards.length} itens ativos prontos para pedido`;

    const renderSearch = () => {
        const rawTerm = searchInput.value.trim();
        const term = normalizeText(rawTerm);
        const termGroups = buildTermGroups(rawTerm);
        let visibleCards = 0;

        const applySearch = (allowPrefixFallback = false) => {
            let totalVisible = 0;

            sections.forEach((section) => {
                const sectionCards = Array.from(section.querySelectorAll("[data-product-card]"));
                let visibleInSection = 0;

                sectionCards.forEach((card) => {
                    const indexText = normalizeText(card.dataset.searchIndex || "");
                    const matches = term === ""
                        || cardMatchesTermGroups(indexText, termGroups, allowPrefixFallback);

                    card.hidden = !matches;

                    if (matches) {
                        visibleInSection += 1;
                    }
                });

                section.hidden = visibleInSection === 0;
                totalVisible += visibleInSection;
            });

            return totalVisible;
        };

        visibleCards = applySearch(false);

        if (term !== "" && visibleCards === 0) {
            visibleCards = applySearch(true);
        }

        if (feedback) {
            feedback.textContent = term === ""
                ? defaultFeedback
                : `${visibleCards} item(ns) encontrado(s) para "${rawTerm}"`;
        }

        if (emptyState) {
            emptyState.hidden = visibleCards !== 0;
        }
    };

    const handleSearchInput = () => {
        renderSearch();
    };

    const handleSearchSubmit = (event) => {
        event.preventDefault();
        renderSearch();
        catalogAnchor?.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    searchInput.addEventListener("input", handleSearchInput);

    if (searchForm) {
        searchForm.addEventListener("submit", handleSearchSubmit);
        searchForm.__catalogSearchCleanup = () => {
            searchInput.removeEventListener("input", handleSearchInput);
            searchForm.removeEventListener("submit", handleSearchSubmit);
            delete searchForm.__catalogSearchCleanup;
        };
    }
}

function initStorefrontInstantNavigation() {
    if (window.__storefrontInstantNavigationInitialized === true) {
        return;
    }

    window.__storefrontInstantNavigationInitialized = true;

    const parser = new DOMParser();
    const pageCache = new Map();
    const warmImageCache = new Set();

    const getUrlKey = (url) => `${url.pathname}${url.search}`;
    const isSameOrigin = (url) => url.origin === window.location.origin;
    const allowedPaths = new Set(["/", "/index.php", "/categoria.php", "/promocoes.php", "/meus-cupons.php", "/busca.php"]);

    const canInstantNavigate = (url, anchor) => {
        if (!(anchor instanceof HTMLAnchorElement)) {
            return false;
        }

        if (!anchor.hasAttribute("data-instant-nav")) {
            return false;
        }

        if (!isSameOrigin(url) || !allowedPaths.has(url.pathname)) {
            return false;
        }

        if (anchor.target && anchor.target !== "_self") {
            return false;
        }

        if (anchor.hasAttribute("download")) {
            return false;
        }

        return true;
    };

    const warmDocumentImages = (doc) => {
        const preloadUrls = Array.from(doc.querySelectorAll('link[rel="preload"][as="image"]'))
            .map((node) => node.getAttribute("href") || "")
            .filter(Boolean);

        preloadUrls.forEach((href) => {
            const absoluteUrl = new URL(href, window.location.href).href;

            if (warmImageCache.has(absoluteUrl)) {
                return;
            }

            warmImageCache.add(absoluteUrl);

            const image = new Image();
            image.decoding = "async";
            image.src = absoluteUrl;

            if (!document.head.querySelector(`link[rel="preload"][as="image"][href="${CSS.escape(href)}"]`)) {
                const preload = document.createElement("link");
                preload.rel = "preload";
                preload.as = "image";
                preload.href = href;
                document.head.appendChild(preload);
            }
        });
    };

    const fetchStorefrontPage = (url, forceReload = false) => {
        const cacheKey = getUrlKey(url);

        if (!forceReload && pageCache.has(cacheKey)) {
            return pageCache.get(cacheKey);
        }

        const request = fetch(url.href, {
            method: "GET",
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            cache: "default",
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Falha ao carregar ${url.href}`);
                }

                const html = await response.text();
                const doc = parser.parseFromString(html, "text/html");
                warmDocumentImages(doc);

                return { html, doc };
            })
            .catch((error) => {
                pageCache.delete(cacheKey);
                throw error;
            });

        pageCache.set(cacheKey, request);

        return request;
    };

    const syncHeaderFromDocument = (doc) => {
        const currentLinks = Array.from(document.querySelectorAll(".storefront-nav__link"));
        const nextLinks = Array.from(doc.querySelectorAll(".storefront-nav__link"));

        if (currentLinks.length > 0 && nextLinks.length > 0) {
            const currentByHref = new Map();

            currentLinks.forEach((link) => {
                const href = link.getAttribute("href");

                if (!href) {
                    return;
                }

                currentByHref.set(new URL(href, window.location.href).href, link);
            });

            nextLinks.forEach((nextLink) => {
                const href = nextLink.getAttribute("href");

                if (!href) {
                    return;
                }

                const currentLink = currentByHref.get(new URL(href, window.location.href).href);

                if (currentLink instanceof HTMLElement) {
                    currentLink.classList.toggle("is-current", nextLink.classList.contains("is-current"));
                }
            });
        }

        const currentSearchForm = document.querySelector("[data-catalog-search-form]");
        const nextSearchForm = doc.querySelector("[data-catalog-search-form]");

        if (currentSearchForm instanceof HTMLFormElement && nextSearchForm instanceof HTMLFormElement) {
            currentSearchForm.action = nextSearchForm.action;
            currentSearchForm.dataset.catalogSearchMode = nextSearchForm.dataset.catalogSearchMode || currentSearchForm.dataset.catalogSearchMode || "global";

            const currentSearchInput = currentSearchForm.querySelector("[data-catalog-search]");
            const nextSearchInput = nextSearchForm.querySelector("[data-catalog-search]");

            if (currentSearchInput instanceof HTMLInputElement && nextSearchInput instanceof HTMLInputElement) {
                currentSearchInput.value = nextSearchInput.value;
                currentSearchInput.placeholder = nextSearchInput.placeholder;
            }
        }

        document.querySelector("[data-utility-menu]")?.classList.remove("is-open");
        document.querySelector("[data-utility-toggle]")?.setAttribute("aria-expanded", "false");
    };

    const syncFlashStackFromDocument = (doc) => {
        document.querySelector(".flash-stack")?.remove();

        const nextFlashStack = doc.querySelector(".flash-stack");

        if (!(nextFlashStack instanceof HTMLElement)) {
            return;
        }

        const header = document.querySelector(".storefront-header");

        if (header && header.parentNode) {
            header.parentNode.insertBefore(nextFlashStack, header.nextSibling);
            return;
        }

        document.body.prepend(nextFlashStack);
    };

    const swapStorefrontContent = (doc, url, historyMode = "push") => {
        const currentStorefront = document.querySelector(".storefront");
        const nextStorefront = doc.querySelector(".storefront");

        if (!(currentStorefront instanceof HTMLElement) || !(nextStorefront instanceof HTMLElement)) {
            window.location.href = url.href;
            return;
        }

        document.title = doc.title || document.title;
        document.body.className = doc.body.className;
        syncHeaderFromDocument(doc);
        syncFlashStackFromDocument(doc);
        currentStorefront.replaceWith(nextStorefront);

        if (historyMode === "push") {
            window.history.pushState({ instantStorefront: true }, "", url.href);
        } else if (historyMode === "replace") {
            window.history.replaceState({ instantStorefront: true }, "", url.href);
        }

        window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        bootCurrentPageUi();
    };

    const navigateInstantly = async (url, historyMode = "push") => {
        if (document.body.dataset.instantNavBusy === "1") {
            return;
        }

        document.body.dataset.instantNavBusy = "1";

        try {
            const payload = await fetchStorefrontPage(url);
            swapStorefrontContent(payload.doc, url, historyMode);
        } catch (error) {
            window.location.href = url.href;
        } finally {
            delete document.body.dataset.instantNavBusy;
        }
    };

    const prefetchAnchor = (anchor) => {
        if (!(anchor instanceof HTMLAnchorElement)) {
            return;
        }

        const url = new URL(anchor.href, window.location.href);

        if (!canInstantNavigate(url, anchor) || getUrlKey(url) === getUrlKey(new URL(window.location.href))) {
            return;
        }

        fetchStorefrontPage(url).catch(() => undefined);
    };

    document.addEventListener("click", (event) => {
        const anchor = event.target.closest("a[data-instant-nav]");

        if (!(anchor instanceof HTMLAnchorElement)) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const url = new URL(anchor.href, window.location.href);

        if (!canInstantNavigate(url, anchor)) {
            return;
        }

        event.preventDefault();
        navigateInstantly(url, "push");
    });

    document.addEventListener("mouseenter", (event) => {
        const anchor = event.target.closest("a[data-instant-nav]");

        if (anchor instanceof HTMLAnchorElement) {
            prefetchAnchor(anchor);
        }
    }, true);

    document.addEventListener("focusin", (event) => {
        const anchor = event.target.closest("a[data-instant-nav]");

        if (anchor instanceof HTMLAnchorElement) {
            prefetchAnchor(anchor);
        }
    });

    document.addEventListener("touchstart", (event) => {
        const anchor = event.target.closest("a[data-instant-nav]");

        if (anchor instanceof HTMLAnchorElement) {
            prefetchAnchor(anchor);
        }
    }, { passive: true });

    window.history.replaceState({ instantStorefront: true }, "", window.location.href);

    window.addEventListener("popstate", () => {
        const currentUrl = new URL(window.location.href);

        if (!allowedPaths.has(currentUrl.pathname)) {
            window.location.reload();
            return;
        }

        navigateInstantly(currentUrl, "replace");
    });

    window.setTimeout(() => {
        document.querySelectorAll("a[data-instant-nav]").forEach((anchor) => {
            if (anchor instanceof HTMLAnchorElement) {
                prefetchAnchor(anchor);
            }
        });
    }, 800);
}

function initProductDetailsToggles() {
    const toggles = document.querySelectorAll("[data-product-details-toggle]");

    toggles.forEach((toggle) => {
        toggle.addEventListener("click", () => {
            const wrapper = toggle.closest(".storefront-product-card__details, .storefront-product-detail__details");
            const content = wrapper?.querySelector("[data-product-details]");

            if (!content) {
                return;
            }

            const isExpanded = toggle.getAttribute("aria-expanded") === "true";
            toggle.setAttribute("aria-expanded", isExpanded ? "false" : "true");
            toggle.textContent = isExpanded ? "Sobre o produto" : "Ocultar detalhes";
            content.hidden = isExpanded;
        });
    });
}

function initProductQuantitySteppers() {
    const scopes = document.querySelectorAll("[data-product-order-scope]");

    scopes.forEach((scope) => {
        const quantityInput = scope.querySelector("[data-product-quantity-input]");
        const stepButtons = scope.querySelectorAll("[data-product-quantity-step]");
        const flavorSelect = scope.querySelector("[data-product-flavor-select]");
        const flavorStockLabel = scope.querySelector("[data-product-flavor-stock]");

        if (!quantityInput || stepButtons.length === 0) {
            return;
        }

        const getDefaultMax = () => {
            const fallback = Number.parseInt(
                quantityInput.dataset.defaultMax || quantityInput.getAttribute("max") || "1",
                10
            );

            return Number.isNaN(fallback) ? 1 : Math.max(1, fallback);
        };

        const getCurrentMax = () => {
            const currentMax = Number.parseInt(quantityInput.getAttribute("max") || "", 10);

            if (!Number.isNaN(currentMax) && currentMax > 0) {
                return currentMax;
            }

            return getDefaultMax();
        };

        const syncQuantity = () => {
            let max = getDefaultMax();
            let disabled = false;

            if (flavorSelect) {
                const selectedValue = flavorSelect.value.trim();
                const flavorRequired = flavorSelect.dataset.productFlavorRequired === "true";
                const selectedOption = flavorSelect.options[flavorSelect.selectedIndex];
                const optionStock = Number.parseInt(selectedOption?.dataset.stock || "", 10);

                if (flavorRequired && selectedValue === "") {
                    disabled = true;
                } else if (!Number.isNaN(optionStock) && optionStock > 0) {
                    max = optionStock;
                } else if (selectedValue !== "") {
                    disabled = true;
                }
            }

            quantityInput.max = `${max}`;
            quantityInput.disabled = disabled;

            let value = Number.parseInt(quantityInput.value || "1", 10);
            value = Number.isNaN(value) ? 1 : value;
            value = Math.min(max, Math.max(1, value));
            quantityInput.value = `${value}`;

            stepButtons.forEach((button) => {
                const step = Number.parseInt(button.dataset.productQuantityStep || "0", 10);
                button.disabled = disabled
                    || (step < 0 && value <= 1)
                    || (step > 0 && value >= max);
            });

            if (flavorStockLabel) {
                flavorStockLabel.hidden = true;
            }
        };

        stepButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (button.disabled) {
                    return;
                }

                const step = Number.parseInt(button.dataset.productQuantityStep || "0", 10);
                const currentValue = Number.parseInt(quantityInput.value || "1", 10) || 1;
                const max = getCurrentMax();
                const nextValue = Math.min(max, Math.max(1, currentValue + step));

                quantityInput.value = `${nextValue}`;
                syncQuantity();
            });
        });

        quantityInput.addEventListener("change", syncQuantity);

        if (flavorSelect) {
            flavorSelect.addEventListener("change", syncQuantity);
        }

        syncQuantity();
    });
}

function initCartQuantitySteppers() {
    const forms = document.querySelectorAll("[data-cart-quantity-form]");

    forms.forEach((form) => {
        const input = form.querySelector("[data-cart-quantity-input]");
        const stepButtons = form.querySelectorAll("[data-cart-quantity-step]");

        if (!input || stepButtons.length === 0) {
            return;
        }

        const getLimits = () => {
            const min = Number.parseInt(input.getAttribute("min") || "1", 10);
            const max = Number.parseInt(input.getAttribute("max") || `${Number.MAX_SAFE_INTEGER}`, 10);

            return {
                min: Number.isNaN(min) ? 1 : min,
                max: Number.isNaN(max) ? Number.MAX_SAFE_INTEGER : max,
            };
        };

        const clampValue = (value) => {
            const limits = getLimits();
            return Math.min(limits.max, Math.max(limits.min, value));
        };

        const syncButtons = () => {
            const limits = getLimits();
            const currentValue = clampValue(Number.parseInt(input.value || `${limits.min}`, 10) || limits.min);

            input.value = `${currentValue}`;

            stepButtons.forEach((button) => {
                const step = Number.parseInt(button.dataset.cartQuantityStep || "0", 10);

                if (step < 0) {
                    button.disabled = currentValue <= limits.min;
                } else if (step > 0) {
                    button.disabled = currentValue >= limits.max;
                }
            });
        };

        const submitForm = () => {
            syncButtons();

            if (typeof form.requestSubmit === "function") {
                form.requestSubmit();
                return;
            }

            form.submit();
        };

        stepButtons.forEach((button) => {
            button.addEventListener("click", () => {
                const step = Number.parseInt(button.dataset.cartQuantityStep || "0", 10);

                if (!step) {
                    return;
                }

                const currentValue = Number.parseInt(input.value || "1", 10) || 1;

                if (step < 0 && currentValue <= 1) {
                    input.value = "0";
                    submitForm();
                    return;
                }

                input.value = `${clampValue(currentValue + step)}`;
                submitForm();
            });
        });

        input.addEventListener("change", () => {
            input.value = `${clampValue(Number.parseInt(input.value || "1", 10) || 1)}`;
            submitForm();
        });

        syncButtons();
    });
}

function showFlashMessage(type, message) {
    if (!message) {
        return;
    }

    let stack = document.querySelector(".flash-stack");

    if (!stack) {
        stack = document.createElement("div");
        stack.className = "flash-stack";
        document.body.appendChild(stack);
    }

    const flash = document.createElement("div");
    flash.className = `flash flash--${type}`;
    flash.textContent = message;
    stack.appendChild(flash);

    window.setTimeout(() => {
        flash.remove();

        if (!stack.children.length) {
            stack.remove();
        }
    }, 2600);
}

function syncGlobalCartIndicators(count) {
    document.querySelectorAll("[data-global-cart-count]").forEach((node) => {
        node.textContent = `${count}`;
    });

    document.querySelectorAll("[data-global-cart-menu-link]").forEach((node) => {
        const baseLabel = node.dataset.baseLabel || "Carrinho";
        node.textContent = count > 0 ? `${baseLabel} (${count})` : baseLabel;
    });
}

function syncCartOverlayMarkup(cart) {
    const body = document.querySelector("[data-cart-overlay-body]");

    if (!body || typeof cart?.overlay_html !== "string") {
        return;
    }

    body.innerHTML = cart.overlay_html;
}

function applySharedCartState(cart) {
    syncGlobalCartIndicators(cart?.count || 0);
    syncCartOverlayMarkup(cart);
}

function showCartPopover(message) {
    const trigger = document.querySelector(".floating-cart");
    const popover = document.querySelector("[data-cart-popover]");
    const messageNode = popover?.querySelector("[data-cart-popover-message]");

    if (!(trigger instanceof HTMLElement) || !(popover instanceof HTMLElement) || !(messageNode instanceof HTMLElement)) {
        showFlashMessage("success", message || "Produto adicionado ao carrinho.");
        return;
    }

    messageNode.textContent = message || "Seu produto foi enviado para o carrinho.";

    if (popover.dataset.hideTimer) {
        window.clearTimeout(Number.parseInt(popover.dataset.hideTimer, 10));
    }

    if (trigger.dataset.bumpTimer) {
        window.clearTimeout(Number.parseInt(trigger.dataset.bumpTimer, 10));
    }

    popover.hidden = false;
    popover.classList.remove("is-visible");
    trigger.classList.remove("is-bumped");
    void popover.offsetWidth;
    popover.classList.add("is-visible");
    trigger.classList.add("is-bumped");
    window.__syncFloatingCartViewportPosition?.();

    const bumpTimer = window.setTimeout(() => {
        trigger.classList.remove("is-bumped");
        delete trigger.dataset.bumpTimer;
    }, 520);

    const hideTimer = window.setTimeout(() => {
        popover.classList.remove("is-visible");

        window.setTimeout(() => {
            popover.hidden = true;
        }, 220);
    }, 2600);

    trigger.dataset.bumpTimer = `${bumpTimer}`;
    popover.dataset.hideTimer = `${hideTimer}`;
}

function initCartOverlay() {
    const trigger = document.querySelector("[data-cart-overlay-trigger]");
    const overlay = document.querySelector("[data-cart-overlay]");

    if (!trigger || !overlay) {
        return;
    }

    const dialog = overlay.querySelector(".cart-overlay__dialog");
    const loading = overlay.querySelector("[data-cart-overlay-loading]");
    const body = overlay.querySelector("[data-cart-overlay-body]");
    const cartUrl = overlay.dataset.cartOverlayUrl || "";
    const csrfToken = overlay.dataset.cartOverlayCsrf || "";
    let isLoading = false;

    const setLoadingState = (active) => {
        if (loading) {
            loading.hidden = !active;
        }

        if (body) {
            body.hidden = active;
        }
    };

    const applyOverlayState = (cart) => {
        if (!body) {
            return;
        }

        body.innerHTML = cart.overlay_html || "";
        body.hidden = false;
        syncGlobalCartIndicators(cart.count || 0);
    };

    const fetchCartState = async () => {
        if (isLoading || !cartUrl) {
            return;
        }

        isLoading = true;
        setLoadingState(true);

        try {
            const response = await fetch(cartUrl, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                cache: "no-store",
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || "Nao foi possivel carregar o carrinho.");
            }

            applyOverlayState(data.cart || { count: 0, overlay_html: "" });
        } catch (error) {
            if (body) {
                body.innerHTML = '<div class="cart-overlay-panel__empty"><strong>Nao foi possivel carregar o carrinho.</strong><p>Tente novamente em instantes.</p></div>';
                body.hidden = false;
            }
            showFlashMessage("error", error.message || "Nao foi possivel carregar o carrinho.");
        } finally {
            isLoading = false;
            setLoadingState(false);
        }
    };

    const sendCartAction = async (action, itemKey, quantity = null) => {
        const formData = new FormData();
        formData.set("csrf_token", csrfToken);
        formData.set("action", action);
        formData.set("ajax", "1");

        if (itemKey) {
            formData.set("item_key", itemKey);
        }

        if (quantity !== null) {
            formData.set("quantity", `${quantity}`);
        }

        const response = await fetch(cartUrl, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Nao foi possivel atualizar o carrinho.");
        }

        applyOverlayState(data.cart || { count: 0, overlay_html: "" });
        return data;
    };

    const openOverlay = async () => {
        overlay.hidden = false;
        document.body.classList.add("cart-overlay-open");
        dialog?.focus();
        await fetchCartState();
    };

    const closeOverlay = () => {
        overlay.hidden = true;
        document.body.classList.remove("cart-overlay-open");
    };

    trigger.addEventListener("click", () => {
        openOverlay();
    });

    overlay.addEventListener("click", (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches("[data-cart-overlay-close]")) {
            closeOverlay();
            return;
        }

        const stepButton = target.closest("[data-cart-overlay-step]");

        if (stepButton instanceof HTMLElement) {
            const itemNode = stepButton.closest("[data-cart-overlay-item]");

            if (!(itemNode instanceof HTMLElement)) {
                return;
            }

            const itemKey = itemNode.dataset.itemKey || "";
            const maxQuantity = Number.parseInt(itemNode.dataset.maxQuantity || "1", 10) || 1;
            const quantityNode = itemNode.querySelector("[data-cart-overlay-quantity]");
            const currentQuantity = Number.parseInt(quantityNode?.textContent || "1", 10) || 1;
            const step = Number.parseInt(stepButton.dataset.cartOverlayStep || "0", 10) || 0;
            const nextQuantity = Math.max(1, Math.min(maxQuantity, currentQuantity + step));

            if (nextQuantity === currentQuantity) {
                return;
            }

            sendCartAction("update", itemKey, nextQuantity).catch((error) => {
                showFlashMessage("error", error.message || "Nao foi possivel atualizar o carrinho.");
            });
            return;
        }

        const removeButton = target.closest("[data-cart-overlay-remove]");

        if (removeButton instanceof HTMLElement) {
            const itemNode = removeButton.closest("[data-cart-overlay-item]");

            if (!(itemNode instanceof HTMLElement)) {
                return;
            }

            const itemKey = itemNode.dataset.itemKey || "";

            sendCartAction("remove", itemKey).catch((error) => {
                showFlashMessage("error", error.message || "Nao foi possivel atualizar o carrinho.");
            });
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !overlay.hidden) {
            closeOverlay();
        }
    });
}

function initCartAjax() {
    const cartPage = document.querySelector("[data-cart-page]");

    if (!cartPage) {
        return;
    }

    const emptyState = cartPage.querySelector("[data-cart-empty]");
    const layout = cartPage.querySelector("[data-cart-layout]");
    const countHeading = cartPage.querySelector("[data-cart-count-heading]");
    const subtotal = cartPage.querySelector("[data-cart-subtotal]");
    const deliveryFee = cartPage.querySelector("[data-cart-delivery-fee]");
    const total = cartPage.querySelector("[data-cart-total]");
    const summaryPanel = cartPage.querySelector("[data-cart-summary-panel]");
    const selectAllInput = cartPage.querySelector("[data-cart-select-all-input]");
    const csrfToken = cartPage.dataset.cartCsrf || "";
    let confirmModal = null;
    let confirmDialog = null;
    let confirmTitle = null;
    let confirmMessage = null;
    let confirmAccept = null;
    let confirmCancel = null;
    let pendingClearForm = null;
    let lastFocusedNode = null;
    let confirmModalBound = false;

    const syncConfirmModalNodes = () => {
        confirmModal = cartPage.querySelector("[data-cart-confirm-modal]");
        confirmDialog = confirmModal?.querySelector(".cart-confirm-modal__dialog") || null;
        confirmTitle = confirmModal?.querySelector("[data-cart-confirm-title]") || null;
        confirmMessage = confirmModal?.querySelector("[data-cart-confirm-message]") || null;
        confirmAccept = confirmModal?.querySelector("[data-cart-confirm-accept]") || null;
        confirmCancel = confirmModal?.querySelector("[data-cart-confirm-cancel]") || null;
    };

    const createConfirmModal = () => {
        cartPage.insertAdjacentHTML(
            "beforeend",
            `
                <div class="cart-confirm-modal" data-cart-confirm-modal hidden>
                    <div class="cart-confirm-modal__backdrop" data-cart-confirm-close></div>
                    <div
                        class="cart-confirm-modal__dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="cart-confirm-title"
                        aria-describedby="cart-confirm-message"
                        tabindex="-1"
                    >
                        <button class="cart-confirm-modal__dismiss" type="button" aria-label="Fechar" data-cart-confirm-close>
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <div class="cart-confirm-modal__icon" aria-hidden="true">!</div>
                        <span class="cart-confirm-modal__eyebrow">Confirmar acao</span>
                        <h3 id="cart-confirm-title" data-cart-confirm-title>Limpar carrinho?</h3>
                        <p id="cart-confirm-message" data-cart-confirm-message></p>
                        <div class="cart-confirm-modal__actions">
                            <button class="btn btn--ghost" type="button" data-cart-confirm-cancel>Cancelar</button>
                            <button class="btn btn--primary cart-confirm-modal__accept" type="button" data-cart-confirm-accept>Sim, limpar</button>
                        </div>
                    </div>
                </div>
            `
        );

        syncConfirmModalNodes();
    };

    const closeConfirmModal = () => {
        if (!(confirmModal instanceof HTMLElement)) {
            return;
        }

        confirmModal.hidden = true;
        document.body.classList.remove("cart-confirm-modal-open");
        pendingClearForm = null;

        if (confirmAccept instanceof HTMLButtonElement) {
            confirmAccept.disabled = false;
        }

        if (confirmCancel instanceof HTMLButtonElement) {
            confirmCancel.disabled = false;
        }

        if (lastFocusedNode instanceof HTMLElement) {
            lastFocusedNode.focus();
        }

        lastFocusedNode = null;
    };

    const bindConfirmModal = () => {
        if (!(confirmModal instanceof HTMLElement) || confirmModalBound) {
            return;
        }

        confirmModal.addEventListener("click", (event) => {
            const target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.matches("[data-cart-confirm-close], [data-cart-confirm-cancel]")) {
                closeConfirmModal();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && confirmModal instanceof HTMLElement && !confirmModal.hidden) {
                closeConfirmModal();
            }
        });

        if (confirmAccept instanceof HTMLButtonElement) {
            confirmAccept.addEventListener("click", async () => {
                const form = pendingClearForm;

                if (!(form instanceof HTMLFormElement)) {
                    closeConfirmModal();
                    return;
                }

                confirmAccept.disabled = true;

                if (confirmCancel instanceof HTMLButtonElement) {
                    confirmCancel.disabled = true;
                }

                try {
                    await sendCartForm(form);
                    closeConfirmModal();
                } catch (error) {
                    closeConfirmModal();
                    showFlashMessage("error", error.message || "Nao foi possivel atualizar o carrinho.");
                }
            });
        }

        confirmModalBound = true;
    };

    const ensureConfirmModal = () => {
        syncConfirmModalNodes();

        if (!(confirmModal instanceof HTMLElement)) {
            createConfirmModal();
        }

        bindConfirmModal();

        return confirmModal instanceof HTMLElement;
    };

    const openConfirmModal = (form) => {
        if (!ensureConfirmModal()) {
            return false;
        }

        pendingClearForm = form;
        lastFocusedNode = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        if (confirmTitle instanceof HTMLElement) {
            confirmTitle.textContent = form.getAttribute("data-confirm-title") || "Limpar carrinho?";
        }

        if (confirmMessage instanceof HTMLElement) {
            confirmMessage.textContent = form.getAttribute("data-confirm-message") || "";
        }

        if (confirmAccept instanceof HTMLButtonElement) {
            confirmAccept.textContent = form.getAttribute("data-confirm-accept-label") || "Confirmar";
            confirmAccept.disabled = false;
        }

        if (confirmCancel instanceof HTMLButtonElement) {
            confirmCancel.disabled = false;
        }

        confirmModal.hidden = false;
        document.body.classList.add("cart-confirm-modal-open");

        if (confirmDialog instanceof HTMLElement) {
            confirmDialog.focus();
        } else if (confirmCancel instanceof HTMLButtonElement) {
            confirmCancel.focus();
        }

        return true;
    };

    ensureConfirmModal();

    const applyCartState = (cart) => {
        syncGlobalCartIndicators(cart.count);

        if (countHeading) {
            countHeading.textContent = cart.count_label;
        }

        if (summaryPanel && cart.summary_html) {
            summaryPanel.innerHTML = cart.summary_html;
        } else {
            if (subtotal) {
                subtotal.textContent = cart.subtotal_formatted;
            }

            if (deliveryFee) {
                deliveryFee.textContent = cart.delivery_fee_formatted;
            }

            if (total) {
                total.textContent = cart.total_formatted;
            }
        }

        const itemsByKey = new Map((cart.items || []).map((item) => [item.key, item]));
        const currentItems = cartPage.querySelectorAll("[data-cart-item]");

        currentItems.forEach((itemNode) => {
            const itemKey = itemNode.dataset.itemKey || "";
            const itemData = itemsByKey.get(itemKey);

            if (!itemData) {
                itemNode.remove();
                return;
            }

            const quantityInput = itemNode.querySelector("[data-cart-quantity-input]");
            const selectionInput = itemNode.querySelector("[data-cart-select-input]");

            if (quantityInput) {
                quantityInput.value = `${itemData.quantity}`;
                quantityInput.max = `${itemData.max_quantity}`;
            }

            if (selectionInput instanceof HTMLInputElement) {
                selectionInput.checked = Boolean(itemData.selected);
            }

            itemNode.classList.toggle("is-unselected", !itemData.selected);
        });

        if (selectAllInput instanceof HTMLInputElement) {
            const selectedItems = (cart.items || []).filter((item) => item.selected);
            selectAllInput.checked = (cart.items || []).length > 0 && selectedItems.length === (cart.items || []).length;
            selectAllInput.indeterminate = selectedItems.length > 0 && selectedItems.length < (cart.items || []).length;
        }

        if (layout && emptyState) {
            layout.hidden = cart.empty;
            emptyState.hidden = !cart.empty;
        }
    };

    const sendCartForm = async (form) => {
        const formData = new FormData(form);
        formData.set("ajax", "1");

        const selectedMethod = cartPage.querySelector("[data-checkout-method]:checked")?.value || "";
        const selectedPayment = cartPage.querySelector("[data-checkout-payment]:checked")?.value || "";

        if (selectedMethod) {
            formData.set("fulfillment_method", selectedMethod);
        }

        if (selectedPayment) {
            formData.set("payment_method", selectedPayment);
        }

        const response = await fetch(form.getAttribute("action") || window.location.href, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Nao foi possivel atualizar o carrinho.");
        }

        applyCartState(data.cart || { count: 0, items: [], empty: true });
        return data;
    };

    cartPage.querySelectorAll("[data-cart-quantity-form], [data-cart-remove-form], [data-cart-clear-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            if (form.matches("[data-cart-clear-form]")) {
                if (!openConfirmModal(form)) {
                    showFlashMessage("error", "Nao foi possivel abrir a confirmacao do carrinho.");
                }
                return;
            }

            try {
                await sendCartForm(form);
            } catch (error) {
                showFlashMessage("error", error.message || "Nao foi possivel atualizar o carrinho.");
            }
        });
    });

    cartPage.addEventListener("change", async (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.matches("[data-cart-select-input]")) {
            const form = target.closest("[data-cart-select-form]");

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            try {
                await sendCartForm(form);
            } catch (error) {
                target.checked = !target.checked;
                showFlashMessage("error", error.message || "Nao foi possivel atualizar a selecao.");
            }

            return;
        }

        if (target.matches("[data-cart-select-all-input]")) {
            const form = target.closest("[data-cart-select-all-form]");

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            try {
                await sendCartForm(form);
            } catch (error) {
                target.checked = !target.checked;
                showFlashMessage("error", error.message || "Nao foi possivel atualizar a selecao.");
            }
        }
    });

    cartPage.addEventListener("change", async (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (!target.matches("[data-checkout-method], [data-checkout-payment]")) {
            return;
        }

        try {
            const formData = new FormData();
            formData.set("csrf_token", csrfToken);
            formData.set("action", "set_checkout_options");
            formData.set(
                "fulfillment_method",
                cartPage.querySelector("[data-checkout-method]:checked")?.value || "delivery"
            );

            const paymentValue = cartPage.querySelector("[data-checkout-payment]:checked")?.value || "";

            if (paymentValue) {
                formData.set("payment_method", paymentValue);
            }

            formData.set("ajax", "1");

            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || "Nao foi possivel atualizar a finalizacao.");
            }

            applyCartState(data.cart || { count: 0, items: [], empty: true });
        } catch (error) {
            showFlashMessage("error", error.message || "Nao foi possivel atualizar a finalizacao.");
        }
    });
}

function initProductCartAjax() {
    const forms = document.querySelectorAll("[data-product-cart-form]");

    forms.forEach((form) => {
        form.addEventListener("submit", async (event) => {
            if (event.defaultPrevented) {
                return;
            }

            event.preventDefault();

            const submitButton = form.querySelector('[type="submit"]');

            if (!(submitButton instanceof HTMLButtonElement)) {
                return;
            }

            if (form.dataset.isSubmitting === "true") {
                return;
            }

            const originalLabel = submitButton.textContent || "Adicionar ao carrinho";
            form.dataset.isSubmitting = "true";
            submitButton.disabled = true;
            submitButton.textContent = "Adicionando...";

            try {
                const formData = new FormData(form);
                formData.set("ajax", "1");

                const response = await fetch(form.getAttribute("action") || window.location.href, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: formData,
                });

                const data = await response.json();

                if (data?.login_url) {
                    window.location.href = data.login_url;
                    return;
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Nao foi possivel adicionar ao carrinho.");
                }

                applySharedCartState(data.cart || { count: 0, overlay_html: "" });
                showCartPopover("Seu produto foi adicionado ao carrinho.");
            } catch (error) {
                showFlashMessage("error", error.message || "Nao foi possivel adicionar ao carrinho.");
            } finally {
                form.dataset.isSubmitting = "false";
                submitButton.disabled = false;
                submitButton.textContent = originalLabel;
            }
        });
    });
}

function initCheckoutOptionsForm() {
    const form = document.querySelector("[data-checkout-options-form]");

    if (!form) {
        return;
    }

    const parseMoneyInput = (value) => {
        const normalized = String(value || "")
            .replace(/\s+/g, "")
            .replace(/\./g, "")
            .replace(",", ".")
            .trim();

        if (normalized === "") {
            return null;
        }

        const parsed = Number.parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const getSelectedScope = () => {
        const selectedScope = form.querySelector('input[name="payment_scope"]:checked');
        return selectedScope instanceof HTMLInputElement ? selectedScope.value : "";
    };

    const getSelectedOnlineMethod = () => {
        const selectedOnlineMethod = form.querySelector('input[name="online_payment_method"]:checked');
        return selectedOnlineMethod instanceof HTMLInputElement ? selectedOnlineMethod.value : "pix";
    };

    const getSelectedDeliveryMethod = () => {
        const selectedDeliveryMethod = form.querySelector('input[name="delivery_payment_method"]:checked');
        return selectedDeliveryMethod instanceof HTMLInputElement ? selectedDeliveryMethod.value : "card";
    };

    const getSelectedCashChoice = () => {
        const selectedCashChoice = form.querySelector('input[name="cash_change_choice"]:checked');
        return selectedCashChoice instanceof HTMLInputElement ? selectedCashChoice.value : "";
    };

    const resolveSelectedPaymentMethod = () => {
        const selectedScope = getSelectedScope();

        if (selectedScope === "online") {
            return getSelectedOnlineMethod();
        }

        if (selectedScope === "on_delivery") {
            return getSelectedDeliveryMethod();
        }

        return "";
    };

    const isCashSelectionValid = () => {
        const paymentMethod = resolveSelectedPaymentMethod();

        if (paymentMethod !== "cash") {
            return true;
        }

        const cashChoice = getSelectedCashChoice();

        if (cashChoice === "exact") {
            return true;
        }

        if (cashChoice !== "change") {
            return false;
        }

        const cashField = form.querySelector("[data-checkout-cash-field]");
        const cashInput = form.querySelector("[data-checkout-cash-change-input]");

        if (!(cashField instanceof HTMLElement) || !(cashInput instanceof HTMLInputElement)) {
            return false;
        }

        const totalValue = Number.parseFloat(cashField.getAttribute("data-checkout-total-value") || "");
        const cashValue = parseMoneyInput(cashInput.value);

        return cashValue !== null && Number.isFinite(totalValue) && cashValue >= totalValue;
    };

    const syncCheckoutSubmissionFields = () => {
        const paymentScopeValue = getSelectedScope();
        const onlineMethodValue = getSelectedOnlineMethod();
        const deliveryMethodValue = getSelectedDeliveryMethod();
        const paymentMethodValue = resolveSelectedPaymentMethod();

        document.querySelectorAll("[data-checkout-payment-scope-hidden]").forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = paymentScopeValue;
            }
        });

        document.querySelectorAll("[data-checkout-online-method-hidden]").forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = onlineMethodValue;
            }
        });

        document.querySelectorAll("[data-checkout-delivery-method-hidden]").forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = deliveryMethodValue;
            }
        });

        document.querySelectorAll("[data-checkout-payment-method-hidden]").forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = paymentMethodValue;
            }
        });
    };

    const syncCheckoutImmediateState = () => {
        const selectedScope = getSelectedScope();
        const selectedOnlineMethod = getSelectedOnlineMethod();
        const selectedDeliveryMethod = getSelectedDeliveryMethod();
        const onlineGroup = form.querySelector(".checkout-payment-scope-group--online");
        const deliveryGroup = form.querySelector(".checkout-payment-scope-group--delivery");
        const onlineCardFields = form.querySelector("[data-checkout-online-card-fields]");
        const cashField = form.querySelector("[data-checkout-cash-field]");

        if (onlineGroup instanceof HTMLElement) {
            onlineGroup.classList.toggle("is-active", selectedScope === "online");
        }

        if (deliveryGroup instanceof HTMLElement) {
            deliveryGroup.classList.toggle("is-active", selectedScope === "on_delivery");
        }

        if (onlineCardFields instanceof HTMLElement) {
            const shouldShowOnlineCardFields = selectedScope === "online" && selectedOnlineMethod === "online_card";
            onlineCardFields.hidden = !shouldShowOnlineCardFields;
            onlineCardFields.classList.toggle("checkout-online-card-fields--hidden", !shouldShowOnlineCardFields);
        }

        if (!(cashField instanceof HTMLElement)) {
            syncCheckoutSubmissionFields();
            return;
        }

        const shouldShowCashField =
            selectedScope === "on_delivery"
            && selectedDeliveryMethod === "cash";

        cashField.hidden = !shouldShowCashField;
        cashField.classList.toggle("checkout-cash-field--hidden", !shouldShowCashField);

        const amountBlock = cashField.querySelector("[data-checkout-cash-amount]");
        const selectedCashChoice = getSelectedCashChoice();
        const shouldShowCashAmount =
            shouldShowCashField
            && selectedCashChoice === "change";

        if (amountBlock instanceof HTMLElement) {
            amountBlock.hidden = !shouldShowCashAmount;
            amountBlock.classList.toggle("checkout-cash-field__amount--hidden", !shouldShowCashAmount);
        }

        syncCheckoutSubmissionFields();
    };

    const syncCashChoiceHidden = () => {
        const selectedValue = getSelectedCashChoice();

        document.querySelectorAll("[data-checkout-cash-choice-hidden]").forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = selectedValue;
            }
        });
    };

    const getCheckoutFinalizeState = () => {
        const baseCanFinalize = form.getAttribute("data-checkout-base-can-finalize") === "1";
        const onlineCardReady = form.getAttribute("data-checkout-online-card-ready") === "1";
        const paymentMethod = resolveSelectedPaymentMethod();

        let showPending = false;
        let title = "Ajuste necessario";
        let message = "";
        let actionHref = form.getAttribute("data-checkout-base-pending-action-href") || "";
        let actionLabel = form.getAttribute("data-checkout-base-pending-action-label") || "";

        if (paymentMethod === "") {
            showPending = true;
            message = "Escolha a forma de pagamento para seguir com o pedido.";
            actionHref = "";
            actionLabel = "";
        } else if (paymentMethod === "online_card" && !onlineCardReady) {
            showPending = true;
            message = "Cartao PagBank ainda nao esta liberado. Por enquanto, use Pix ou pagamento na entrega.";
            actionHref = "";
            actionLabel = "";
        } else if (paymentMethod === "cash" && !isCashSelectionValid()) {
            showPending = true;
            message = "Informe um valor valido para o troco ou escolha outra forma de pagamento.";
            actionHref = "";
            actionLabel = "";
        } else if (!baseCanFinalize) {
            showPending = true;
            title = form.getAttribute("data-checkout-base-pending-title") || "Ajuste necessario";
            message = form.getAttribute("data-checkout-base-pending-message") || "";
        }

        return { showPending, title, message, actionHref, actionLabel, paymentMethod };
    };

    const syncCheckoutFinalizeState = () => {
        const pending = document.querySelector("[data-checkout-pending-state]");
        const pendingTitle = document.querySelector("[data-checkout-pending-title]");
        const pendingMessage = document.querySelector("[data-checkout-pending-message]");
        const pendingAction = document.querySelector("[data-checkout-pending-action]");
        const { showPending, title, message, actionHref, actionLabel } = getCheckoutFinalizeState();

        if (pending instanceof HTMLElement) {
            pending.hidden = !showPending;
        }

        if (pendingTitle instanceof HTMLElement) {
            pendingTitle.textContent = title;
        }

        if (pendingMessage instanceof HTMLElement) {
            pendingMessage.textContent = message;
        }

        if (pendingAction instanceof HTMLAnchorElement) {
            if (showPending && actionHref !== "" && actionLabel !== "") {
                pendingAction.hidden = false;
                pendingAction.href = actionHref;
                pendingAction.textContent = actionLabel;
            } else {
                pendingAction.hidden = true;
            }
        }
    };

    const syncMirroredCheckoutFields = () => {
        const sources = form.querySelectorAll("[data-checkout-sync]");

        sources.forEach((source) => {
            if (!(source instanceof HTMLInputElement || source instanceof HTMLTextAreaElement || source instanceof HTMLSelectElement)) {
                return;
            }

            const fieldName = source.getAttribute("data-checkout-sync");

            if (!fieldName) {
                return;
            }

            document.querySelectorAll("[data-checkout-sync-target]").forEach((target) => {
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                if (target.getAttribute("data-checkout-sync-target") !== fieldName) {
                    return;
                }

                target.value = source instanceof HTMLInputElement && source.type === "checkbox"
                    ? (source.checked ? "1" : "0")
                    : source.value;
            });
        });
    };

    const syncBillingAddressState = () => {
        const billingSameInput = form.querySelector("[data-checkout-billing-same]");
        const billingFields = form.querySelector("[data-checkout-billing-fields]");

        if (!(billingSameInput instanceof HTMLInputElement) || !(billingFields instanceof HTMLElement)) {
            return;
        }

        billingFields.hidden = billingSameInput.checked;
        billingFields.classList.toggle("checkout-billing-fields--hidden", billingSameInput.checked);
    };

    const formatCardNumber = (value) => value.replace(/\D+/g, "").slice(0, 19).replace(/(\d{4})(?=\d)/g, "$1 ").trim();
    const formatCardExpiry = (value) => {
        const digits = value.replace(/\D+/g, "").slice(0, 4);

        if (digits.length <= 2) {
            return digits;
        }

        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    };
    const formatDigitsOnly = (value, maxLength) => value.replace(/\D+/g, "").slice(0, maxLength);
    const formatPostalCode = (value) => {
        const digits = value.replace(/\D+/g, "").slice(0, 8);

        if (digits.length <= 5) {
            return digits;
        }

        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    const bindChangeHandlers = () => {
        const applyCheckoutRadioState = (input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            if (input.name === "online_payment_method") {
                const onlineScope = form.querySelector('input[name="payment_scope"][value="online"]');

                if (onlineScope instanceof HTMLInputElement) {
                    onlineScope.checked = true;
                }
            }

            if (input.name === "delivery_payment_method") {
                const deliveryScope = form.querySelector('input[name="payment_scope"][value="on_delivery"]');

                if (deliveryScope instanceof HTMLInputElement) {
                    deliveryScope.checked = true;
                }
            }

            syncCheckoutImmediateState();
            syncCashChoiceHidden();
            syncMirroredCheckoutFields();
            syncCheckoutFinalizeState();
        };

        form.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.addEventListener("change", () => {
                applyCheckoutRadioState(input);
            });

            if (input.name === "online_payment_method" || input.name === "delivery_payment_method") {
                input.addEventListener("click", () => {
                    window.requestAnimationFrame(() => {
                        if (!(input instanceof HTMLInputElement)) {
                            return;
                        }

                        input.checked = true;
                        applyCheckoutRadioState(input);
                    });
                });
            }
        });

        const cashInput = form.querySelector("[data-checkout-cash-change-input]");

        if (cashInput instanceof HTMLInputElement) {
              const syncCashChangeValue = () => {
                  document.querySelectorAll("[data-checkout-cash-change-hidden]").forEach((input) => {
                      if (input instanceof HTMLInputElement) {
                          input.value = cashInput.value;
                      }
                });
            };

            syncCashChangeValue();
            cashInput.addEventListener("input", () => {
                syncCashChangeValue();
                syncCheckoutImmediateState();
                syncCheckoutFinalizeState();
            });

            cashInput.addEventListener("change", () => {
                syncCashChangeValue();
                syncCheckoutImmediateState();
                syncCheckoutFinalizeState();
            });
        }

        const billingSameInput = form.querySelector("[data-checkout-billing-same]");

        if (billingSameInput instanceof HTMLInputElement) {
            billingSameInput.addEventListener("change", () => {
                syncBillingAddressState();
                syncMirroredCheckoutFields();
            });
        }

        form.querySelectorAll("[data-checkout-cash-choice]").forEach((choice) => {
            if (!(choice instanceof HTMLInputElement)) {
                return;
            }

            choice.addEventListener("change", () => {
                const cashInput = form.querySelector("[data-checkout-cash-change-input]");
                const cashField = form.querySelector("[data-checkout-cash-field]");
                const totalInputValue = cashField instanceof HTMLElement
                    ? (cashField.getAttribute("data-checkout-total-input-value") || "")
                    : "";

                if (cashInput instanceof HTMLInputElement) {
                    if (choice.value === "exact" && choice.checked) {
                        cashInput.value = totalInputValue;
                    } else if (choice.value === "change" && choice.checked) {
                        const normalizedCurrent = cashInput.value.replace(/\s+/g, "");
                        const normalizedExact = totalInputValue.replace(/\s+/g, "");

                        if (normalizedCurrent === normalizedExact) {
                            cashInput.value = "";
                        }
                    }
                }

                syncCheckoutImmediateState();
                syncCashChoiceHidden();
                syncMirroredCheckoutFields();
                syncCheckoutFinalizeState();

                if (cashInput instanceof HTMLInputElement && choice.value === "change" && choice.checked) {
                    cashInput.focus();
                }
            });
        });

        form.querySelectorAll("[data-checkout-sync]").forEach((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
                return;
            }

            field.addEventListener("input", () => {
                if (field instanceof HTMLInputElement && field.hasAttribute("data-checkout-card-number")) {
                    field.value = formatCardNumber(field.value);
                }

                if (field instanceof HTMLInputElement && field.hasAttribute("data-checkout-card-expiry")) {
                    field.value = formatCardExpiry(field.value);
                }

                if (field instanceof HTMLInputElement && field.hasAttribute("data-checkout-card-cvv")) {
                    field.value = formatDigitsOnly(field.value, 4);
                }

                if (field instanceof HTMLInputElement && field.hasAttribute("data-checkout-billing-postal-code")) {
                    field.value = formatPostalCode(field.value);
                }

                syncMirroredCheckoutFields();
            });

            field.addEventListener("change", () => {
                syncMirroredCheckoutFields();
            });
        });
      };

      syncCheckoutImmediateState();
      syncBillingAddressState();
      bindChangeHandlers();
      syncCashChoiceHidden();
      syncMirroredCheckoutFields();
      syncCheckoutFinalizeState();

      const placeOrderForm = document.querySelector("[data-checkout-place-order-form]");
      const pixModal = document.querySelector("[data-checkout-pix-modal]");

    if (!(placeOrderForm instanceof HTMLFormElement) || !(pixModal instanceof HTMLElement)) {
        return;
    }

    const dialog = pixModal.querySelector(".checkout-pix-modal__dialog");
    const confirmButton = pixModal.querySelector("[data-checkout-pix-confirm]");
    const qrPanel = pixModal.querySelector("[data-checkout-pix-qr-panel]");
    const qrToggleButton = pixModal.querySelector("[data-checkout-pix-toggle-qr]");
    let allowPixSubmit = false;

    const resetPixQrPanel = () => {
        if (qrPanel instanceof HTMLElement) {
            qrPanel.hidden = true;
        }

        if (qrToggleButton instanceof HTMLButtonElement) {
            qrToggleButton.textContent = qrToggleButton.dataset.labelOpen || "Prefiro QR Code";
            qrToggleButton.setAttribute("aria-expanded", "false");
        }
    };

    const togglePixQrPanel = () => {
        if (!(qrPanel instanceof HTMLElement) || !(qrToggleButton instanceof HTMLButtonElement)) {
            return;
        }

        const willOpen = qrPanel.hidden;
        qrPanel.hidden = !willOpen;
        qrToggleButton.textContent = willOpen
            ? qrToggleButton.dataset.labelClose || "Ocultar QR Code"
            : qrToggleButton.dataset.labelOpen || "Prefiro QR Code";
        qrToggleButton.setAttribute("aria-expanded", willOpen ? "true" : "false");
    };

    const openPixModal = () => {
        resetPixQrPanel();
        pixModal.hidden = false;
        document.body.classList.add("checkout-pix-modal-open");

        if (dialog instanceof HTMLElement) {
            dialog.focus();
        }
    };

    const closePixModal = () => {
        resetPixQrPanel();
        pixModal.hidden = true;
        document.body.classList.remove("checkout-pix-modal-open");
    };

    placeOrderForm.addEventListener("submit", (event) => {
        const paymentInput = placeOrderForm.querySelector("[data-checkout-payment-method-hidden]");
        const paymentMethod = paymentInput instanceof HTMLInputElement ? paymentInput.value : "";
        const finalizeState = getCheckoutFinalizeState();

        if (finalizeState.showPending) {
            event.preventDefault();
            syncCheckoutFinalizeState();

            const pendingMessage = document.querySelector("[data-checkout-pending-state]");
            if (pendingMessage instanceof HTMLElement) {
                pendingMessage.scrollIntoView({ behavior: "smooth", block: "nearest" });
            }
            return;
        }

        if (paymentMethod !== "pix" || allowPixSubmit) {
            allowPixSubmit = false;
            return;
        }

        event.preventDefault();
        openPixModal();
    });

    pixModal.addEventListener("click", (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest("[data-checkout-pix-toggle-qr]")) {
            togglePixQrPanel();
            return;
        }

        if (target.closest("[data-checkout-pix-close]")) {
            closePixModal();
        }
    });

    if (dialog instanceof HTMLElement) {
        dialog.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closePixModal();
            }
        });
    }

    if (confirmButton instanceof HTMLButtonElement) {
        confirmButton.addEventListener("click", () => {
            allowPixSubmit = true;
            closePixModal();

            if (typeof placeOrderForm.requestSubmit === "function") {
                placeOrderForm.requestSubmit();
                return;
            }

            placeOrderForm.submit();
        });
    }
}

function initCopyButtons() {
    document.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-copy-text]");

        if (!button) {
            return;
        }

        const value = button.getAttribute("data-copy-text") || "";

        if (!value.trim()) {
            return;
        }

        const defaultLabel = button.getAttribute("data-copy-label-default") || button.textContent.trim() || "Copiar";
        const successLabel = button.getAttribute("data-copy-label-success") || "Copiado";
        const errorLabel = button.getAttribute("data-copy-label-error") || "Nao foi possivel copiar";

        button.disabled = true;

        try {
            await navigator.clipboard.writeText(value);
            button.textContent = successLabel;
        } catch (error) {
            button.textContent = errorLabel;
        }

        window.setTimeout(() => {
            button.textContent = defaultLabel;
            button.disabled = false;
        }, 1800);
    });
}

function initProductShare() {
    const shareRoots = Array.from(document.querySelectorAll("[data-share-inline-root]"));

    if (shareRoots.length === 0) {
        return;
    }

    shareRoots.forEach((root) => {
        const nativeButton = root.querySelector("[data-share-native]");

        if (nativeButton) {
            if (typeof navigator.share === "function") {
                nativeButton.hidden = false;
            } else {
                nativeButton.remove();
            }
        }

        nativeButton?.addEventListener("click", async () => {
            const shareTitle = root.dataset.shareTitle || document.title;
            const shareText = root.dataset.shareText || "";
            const shareUrl = root.dataset.shareUrl || window.location.href;

            try {
                await navigator.share({
                    title: shareTitle,
                    text: shareText,
                    url: shareUrl,
                });
            } catch (error) {
                if (error && error.name === "AbortError") {
                    return;
                }
            }
        });
    });
}

function initProductFavorites() {
    document.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-favorite-toggle]");

        if (!button) {
            return;
        }

        if (button.disabled) {
            return;
        }

        const url = button.dataset.favoriteUrl || "";
        const productId = button.dataset.favoriteProductId || "";
        const csrfToken = button.dataset.favoriteCsrf || "";
        const labelNode = button.querySelector("[data-favorite-label]");

        if (!url || !productId || !csrfToken) {
            return;
        }

        button.disabled = true;

        try {
            const body = new URLSearchParams();
            body.set("csrf_token", csrfToken);
            body.set("product_id", productId);
            body.set("ajax", "1");

            const response = await fetch(url, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: body.toString(),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                if (data?.login_url) {
                    window.location.href = data.login_url;
                    return;
                }

                throw new Error(data?.message || "Nao foi possivel salvar a peca.");
            }

            const isSaved = Boolean(data.saved);
            button.classList.toggle("is-active", isSaved);
            button.setAttribute("aria-pressed", isSaved ? "true" : "false");
            button.setAttribute("aria-label", isSaved ? "Remover dos salvos" : "Salvar nos seus salvos");

            if (labelNode) {
                labelNode.textContent = isSaved ? "Remover dos salvos" : "Salvar nos seus salvos";
            }
        } catch (error) {
            button.classList.add("is-error");

            window.setTimeout(() => {
                button.classList.remove("is-error");
            }, 1600);
        } finally {
            button.disabled = false;
        }
    });
}

function initTrackingAutoRefresh() {
    let liveRoot = document.querySelector("[data-tracking-live-root]");

    if (!liveRoot) {
        return;
    }

    const refreshUrl = liveRoot.dataset.trackingLiveUrl || window.location.href;
    const pollInterval = Number.parseInt(liveRoot.dataset.trackingLivePoll || "5000", 10);

    if (!refreshUrl || Number.isNaN(pollInterval) || pollInterval < 1000) {
        return;
    }

    let isRefreshing = false;

    const refreshTracking = async () => {
        if (isRefreshing || document.hidden) {
            return;
        }

        isRefreshing = true;

        try {
            const response = await fetch(refreshUrl, {
                method: "GET",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
                cache: "no-store",
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");
            const nextRoot = doc.querySelector("[data-tracking-live-root]");

            if (!nextRoot) {
                return;
            }

            const currentSignature = liveRoot.dataset.trackingLiveSignature || "";
            const nextSignature = nextRoot.dataset.trackingLiveSignature || "";

            if (currentSignature === nextSignature) {
                return;
            }

            liveRoot.replaceWith(nextRoot);
            liveRoot = nextRoot;
        } catch (error) {
            return;
        } finally {
            isRefreshing = false;
        }
    };

    window.setInterval(refreshTracking, pollInterval);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            refreshTracking();
        }
    });
}

function initProductFlavorOrdering() {
    const scopes = document.querySelectorAll("[data-product-order-scope]");

    scopes.forEach((scope) => {
        const orderLink = scope.querySelector("[data-product-order-link]");
        const cartForm = scope.querySelector("[data-product-cart-form]");
        const cartFlavorInput = scope.querySelector("[data-product-cart-flavor-input]");
        const flavorSelect = scope.querySelector("[data-product-flavor-select]");
        const flavorHint = scope.querySelector("[data-product-flavor-hint]");
        const sizeList = scope.querySelector("[data-product-size-list]");
        const sizeOptions = scope.querySelectorAll("[data-product-size-option]");
        const submitButton = scope.querySelector("[data-product-submit]");

        if (!flavorSelect || (!orderLink && !cartForm)) {
            return;
        }

        const baseHref = orderLink?.dataset.baseHref || orderLink?.getAttribute("href") || "";
        const flavorRequired = flavorSelect.dataset.productFlavorRequired === "true";

        const syncSizeSelection = () => {
            const selectedFlavor = flavorSelect.value.trim();

            sizeOptions.forEach((button) => {
                const isSelected = (button.dataset.productSizeValue || "").trim() === selectedFlavor;
                button.classList.toggle("is-selected", isSelected);
                button.setAttribute("aria-pressed", isSelected ? "true" : "false");
            });
        };

        const syncSubmitVisibility = () => {
            if (!submitButton) {
                return;
            }

            const selectedOption = flavorSelect.options[flavorSelect.selectedIndex];
            const optionStock = Number.parseInt(selectedOption?.dataset.stock || "", 10);
            const hasSelection = flavorSelect.value.trim() !== "";
            const selectionAvailable = hasSelection && (Number.isNaN(optionStock) || optionStock > 0);

            submitButton.hidden = flavorRequired ? !selectionAvailable : !hasSelection;
        };

        const focusSelectionControl = () => {
            if (sizeOptions.length > 0) {
                const enabledOptions = Array.from(sizeOptions).filter((button) => !button.disabled);
                const selectedOption = enabledOptions.find((button) => button.classList.contains("is-selected"));
                (selectedOption || enabledOptions[0] || sizeOptions[0]).focus();
                return;
            }

            flavorSelect.focus();
        };

        const setHint = (message, isError = false) => {
            if (!flavorHint) {
                return;
            }

            flavorHint.hidden = message === "";
            flavorHint.textContent = message;
            flavorHint.classList.toggle("is-error", isError);
        };

        const updateOrderLink = () => {
            const selectedFlavor = flavorSelect.value.trim();

            flavorSelect.classList.remove("is-invalid");
            sizeList?.classList.remove("is-invalid");

            if (selectedFlavor !== "") {
                setHint("", false);
            } else if (!flavorRequired) {
                setHint("", false);
            }

            if (cartFlavorInput) {
                cartFlavorInput.value = selectedFlavor;
            }

            syncSizeSelection();
            syncSubmitVisibility();

            if (baseHref === "" || !orderLink) {
                return;
            }

            const url = new URL(baseHref);
            const baseMessage = url.searchParams.get("text") || "";

            if (selectedFlavor !== "") {
                url.searchParams.set("text", `${baseMessage}\nTamanho: ${selectedFlavor}`);
            } else {
                url.searchParams.set("text", baseMessage);
            }

            orderLink.setAttribute("href", url.toString());
        };

        sizeOptions.forEach((button) => {
            button.addEventListener("click", () => {
                const nextValue = (button.dataset.productSizeValue || "").trim();

                if (nextValue === "") {
                    return;
                }

                flavorSelect.value = nextValue;
                flavorSelect.dispatchEvent(new Event("change", { bubbles: true }));
            });
        });

        flavorSelect.addEventListener("change", updateOrderLink);

        if (orderLink) {
            orderLink.addEventListener("click", (event) => {
                if (!flavorRequired || flavorSelect.value.trim() !== "") {
                    updateOrderLink();
                    return;
                }

                event.preventDefault();
                flavorSelect.classList.add("is-invalid");
                sizeList?.classList.add("is-invalid");
                setHint("Escolha um tamanho para continuar.", true);
                focusSelectionControl();
            });
        }

        if (cartForm) {
            cartForm.addEventListener("submit", (event) => {
                if (!flavorRequired || flavorSelect.value.trim() !== "") {
                    updateOrderLink();
                    return;
                }

                event.preventDefault();
                flavorSelect.classList.add("is-invalid");
                sizeList?.classList.add("is-invalid");
                setHint("Escolha um tamanho para adicionar ao carrinho.", true);
                focusSelectionControl();
            });
        }

        updateOrderLink();
    });
}

function initProductGallery() {
    const galleries = document.querySelectorAll("[data-product-gallery-scope]");

    galleries.forEach((gallery) => {
        const mainImage = gallery.querySelector("[data-product-gallery-main]");
        const mainFrame = gallery.querySelector(".storefront-product-detail__main");
        const thumbs = gallery.querySelectorAll("[data-product-gallery-thumb]");

        if (!mainImage || !mainFrame || thumbs.length === 0) {
            return;
        }

        let currentIndex = Array.from(thumbs).findIndex((thumb) => thumb.classList.contains("is-active"));
        currentIndex = currentIndex >= 0 ? currentIndex : 0;
        let dragState = null;

        const activateThumb = (index) => {
            const nextThumb = thumbs[index];

            if (!nextThumb) {
                return;
            }

            const src = nextThumb.dataset.imageSrc || "";
            const alt = nextThumb.dataset.imageAlt || mainImage.getAttribute("alt") || "";

            if (src === "") {
                return;
            }

            currentIndex = index;
            mainImage.setAttribute("src", src);
            mainImage.setAttribute("alt", alt);
            thumbs.forEach((item) => item.classList.remove("is-active"));
            nextThumb.classList.add("is-active");
            nextThumb.scrollIntoView({ block: "nearest", inline: "nearest", behavior: "smooth" });
        };

        const moveGallery = (direction) => {
            if (thumbs.length <= 1) {
                return;
            }

            const totalItems = thumbs.length;
            const nextIndex = (currentIndex + direction + totalItems) % totalItems;

            activateThumb(nextIndex);
        };

        mainImage.setAttribute("draggable", "false");

        thumbs.forEach((thumb, index) => {
            const thumbImage = thumb.querySelector("img");

            thumbImage?.setAttribute("draggable", "false");
            thumb.addEventListener("click", () => {
                activateThumb(index);
            });
        });

        mainFrame.addEventListener("pointerdown", (event) => {
            if (event.pointerType === "mouse" && event.button !== 0) {
                return;
            }

            dragState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                lastX: event.clientX,
                lastY: event.clientY,
            };

            mainFrame.classList.add("is-dragging");
            mainFrame.setPointerCapture?.(event.pointerId);
        });

        mainFrame.addEventListener("pointermove", (event) => {
            if (!dragState || dragState.pointerId !== event.pointerId) {
                return;
            }

            dragState.lastX = event.clientX;
            dragState.lastY = event.clientY;
        });

        const releaseDrag = (event) => {
            if (!dragState || dragState.pointerId !== event.pointerId) {
                return;
            }

            const deltaX = dragState.lastX - dragState.startX;
            const deltaY = dragState.lastY - dragState.startY;
            const horizontalSwipe = Math.abs(deltaX) > 48 && Math.abs(deltaX) > Math.abs(deltaY) * 1.15;

            mainFrame.classList.remove("is-dragging");
            mainFrame.releasePointerCapture?.(event.pointerId);

            if (horizontalSwipe) {
                moveGallery(deltaX < 0 ? 1 : -1);
            }

            dragState = null;
        };

        mainFrame.addEventListener("pointerup", releaseDrag);
        mainFrame.addEventListener("pointercancel", releaseDrag);
        mainFrame.addEventListener("lostpointercapture", () => {
            dragState = null;
            mainFrame.classList.remove("is-dragging");
        });
    });
}

function initProductCardNavigation() {
    const cards = document.querySelectorAll("[data-product-view-url]");

    cards.forEach((card) => {
        card.addEventListener("click", (event) => {
            if (event.defaultPrevented) {
                return;
            }

            if (event.target.closest("a, button, select, option, input, label, textarea")) {
                return;
            }

            const url = card.dataset.productViewUrl || "";

            if (url !== "") {
                window.location.href = url;
            }
        });
    });
}

function initSignupForm() {
    const signupForm = document.querySelector("[data-signup-form]");

    if (!signupForm) {
        return;
    }

    const phoneInput = signupForm.querySelector("#telefone");
    const cpfInput = signupForm.querySelector("#cpf");
    const cpfHint = signupForm.querySelector("[data-cpf-hint]");
    const birthDateInput = signupForm.querySelector("[data-birthdate-input]");
    const birthDateHint = signupForm.querySelector("[data-birthdate-hint]");
    const cepInput = signupForm.querySelector("[data-cep-input]");
    const streetField = signupForm.querySelector("[data-address-street]");
    const districtField = signupForm.querySelector("[data-address-district]");
    const cityField = signupForm.querySelector("[data-address-city]");
    const stateField = signupForm.querySelector("[data-address-state]");
    const cepStatus = signupForm.querySelector("[data-cep-status]");
    const emailInput = signupForm.querySelector("[data-email-input]");
    const emailHint = signupForm.querySelector("[data-email-hint]");
    const emailConfirmationInput = signupForm.querySelector("[data-email-confirmation]");
    const emailConfirmationHint = signupForm.querySelector("[data-email-confirmation-hint]");
    const passwordInput = signupForm.querySelector("[data-password-input]");
    const passwordHint = signupForm.querySelector("[data-password-hint]");
    const passwordConfirmationInput = signupForm.querySelector("[data-password-confirmation]");
    const passwordConfirmationHint = signupForm.querySelector("[data-password-confirmation-hint]");

    const onlyDigits = (value) => value.replace(/\D+/g, "");

    const formatPhone = (value) => {
        const digits = onlyDigits(value).slice(0, 11);

        if (digits.length <= 2) {
            return digits;
        }

        if (digits.length <= 7) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
        }

        if (digits.length <= 10) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
        }

        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
    };

    const formatCpf = (value) => {
        const digits = onlyDigits(value).slice(0, 11);

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return `${digits.slice(0, 3)}.${digits.slice(3)}`;
        }

        if (digits.length <= 9) {
            return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
        }

        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    };

    const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());

    const isValidCpf = (value) => {
        const cpf = onlyDigits(value);

        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }

        for (let digit = 9; digit < 11; digit += 1) {
            let sum = 0;

            for (let index = 0; index < digit; index += 1) {
                sum += Number(cpf[index]) * ((digit + 1) - index);
            }

            const checkDigit = ((10 * sum) % 11) % 10;

            if (checkDigit !== Number(cpf[digit])) {
                return false;
            }
        }

        return true;
    };

    const isValidBirthDate = (value) => {
        if (!value) {
            return false;
        }

        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

        if (!match) {
            return false;
        }

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (
            date.getFullYear() !== year
            || date.getMonth() !== month - 1
            || date.getDate() !== day
        ) {
            return false;
        }

        if (year < 1900 || date > today) {
            return false;
        }

        return true;
    };

    const formatCep = (value) => {
        const digits = onlyDigits(value).slice(0, 8);

        if (digits.length <= 5) {
            return digits;
        }

        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    const setCepStatus = (message, type = "") => {
        if (!cepStatus) {
            return;
        }

        cepStatus.textContent = message;
        cepStatus.classList.remove("is-loading", "is-success", "is-error");

        if (type) {
            cepStatus.classList.add(type);
        }
    };

    const updateCepFormatFeedback = () => {
        if (!cepInput) {
            return true;
        }

        const cep = onlyDigits(cepInput.value);

        if (cep.length === 0) {
            setCepStatus("");
            return false;
        }

        if (cep.length < 8) {
            setCepStatus("");
            return false;
        }

        if (cepStatus && !cepStatus.classList.contains("is-success")) {
            setCepStatus("");
        }

        return true;
    };

    if (phoneInput) {
        phoneInput.addEventListener("input", () => {
            phoneInput.value = formatPhone(phoneInput.value);
        });
    }

    if (cpfInput) {
        cpfInput.addEventListener("input", () => {
            cpfInput.value = formatCpf(cpfInput.value);
        });
    }

    const updateCpfFeedback = () => {
        if (!cpfInput || !cpfHint) {
            return true;
        }

        const cpf = onlyDigits(cpfInput.value);
        cpfHint.classList.remove("is-error", "is-success");

        if (cpf.length === 0) {
            cpfHint.textContent = "";
            return false;
        }

        if (cpf.length < 11) {
            cpfHint.textContent = "CPF incompleto. Digite os 11 numeros.";
            cpfHint.classList.add("is-error");
            return false;
        }

        if (!isValidCpf(cpf)) {
            cpfHint.textContent = "CPF invalido. Confira os numeros informados.";
            cpfHint.classList.add("is-error");
            return false;
        }

        cpfHint.textContent = "CPF valido.";
        cpfHint.classList.add("is-success");
        return true;
    };

    if (cpfInput) {
        cpfInput.addEventListener("input", updateCpfFeedback);
        cpfInput.addEventListener("blur", updateCpfFeedback);
    }

    const updateBirthDateFeedback = () => {
        if (!birthDateInput || !birthDateHint) {
            return true;
        }

        birthDateHint.classList.remove("is-error", "is-success");

        if (birthDateInput.value.length === 0) {
            birthDateHint.textContent = "";
            return false;
        }

        if (!isValidBirthDate(birthDateInput.value)) {
            birthDateHint.textContent = "Data de nascimento invalida. Confira o dia, mes e ano.";
            birthDateHint.classList.add("is-error");
            return false;
        }

        birthDateHint.textContent = "Data de nascimento valida.";
        birthDateHint.classList.add("is-success");
        return true;
    };

    if (birthDateInput) {
        birthDateInput.addEventListener("input", updateBirthDateFeedback);
        birthDateInput.addEventListener("change", updateBirthDateFeedback);
        birthDateInput.addEventListener("blur", updateBirthDateFeedback);
    }

    const updateEmailFeedback = () => {
        if (!emailInput || !emailHint) {
            return true;
        }

        emailHint.classList.remove("is-error", "is-success");

        if (emailInput.value.length === 0) {
            emailHint.textContent = "";
            return false;
        }

        if (!isValidEmail(emailInput.value)) {
            emailHint.textContent = "Email invalido. Confira o formato, por exemplo: nome@dominio.com.";
            emailHint.classList.add("is-error");
            return false;
        }

        emailHint.textContent = "Email valido.";
        emailHint.classList.add("is-success");
        return true;
    };

    const updateEmailConfirmationFeedback = () => {
        if (!emailInput || !emailConfirmationInput || !emailConfirmationHint) {
            return true;
        }

        emailConfirmationHint.classList.remove("is-error", "is-success");

        if (emailConfirmationInput.value.length === 0) {
            emailConfirmationHint.textContent = "Repita o email para evitar erros de cadastro.";
            return false;
        }

        if (emailInput.value.trim().toLowerCase() !== emailConfirmationInput.value.trim().toLowerCase()) {
            emailConfirmationHint.textContent = "Os emails nao conferem.";
            emailConfirmationHint.classList.add("is-error");
            return false;
        }

        emailConfirmationHint.textContent = "Os emails conferem.";
        emailConfirmationHint.classList.add("is-success");
        return true;
    };

    const updatePasswordFeedback = () => {
        if (!passwordInput || !passwordHint) {
            return;
        }

        const password = passwordInput.value;
        const hasMinimumLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasSpecialCharacter = /[^a-zA-Z0-9]/.test(password);

        passwordHint.classList.remove("is-error", "is-success");

        if (password.length === 0) {
            passwordHint.textContent = "Use pelo menos 8 caracteres, uma letra maiuscula e um caractere especial.";
            return;
        }

        if (!hasMinimumLength) {
            passwordHint.textContent = "Senha fraca: faltam pelo menos 8 caracteres.";
            passwordHint.classList.add("is-error");
            return;
        }

        if (!hasUppercase) {
            passwordHint.textContent = "Senha incompleta: adicione pelo menos uma letra maiuscula.";
            passwordHint.classList.add("is-error");
            return;
        }

        if (!hasSpecialCharacter) {
            passwordHint.textContent = "Senha incompleta: adicione pelo menos um caractere especial, como @, ! ou #.";
            passwordHint.classList.add("is-error");
            return;
        }

        passwordHint.textContent = "Senha forte dentro do formato exigido.";
        passwordHint.classList.add("is-success");
    };

    const updatePasswordConfirmationFeedback = () => {
        if (!passwordConfirmationInput || !passwordConfirmationHint || !passwordInput) {
            return;
        }

        passwordConfirmationHint.classList.remove("is-error", "is-success");

        if (passwordConfirmationInput.value.length === 0) {
            passwordConfirmationHint.textContent = "Repita a senha exatamente como acima.";
            return;
        }

        if (passwordConfirmationInput.value !== passwordInput.value) {
            passwordConfirmationHint.textContent = "As senhas nao conferem.";
            passwordConfirmationHint.classList.add("is-error");
            return;
        }

        passwordConfirmationHint.textContent = "As senhas conferem.";
        passwordConfirmationHint.classList.add("is-success");
    };

    if (passwordInput) {
        passwordInput.addEventListener("input", () => {
            updatePasswordFeedback();
            updatePasswordConfirmationFeedback();
        });
    }

    if (emailInput) {
        emailInput.addEventListener("input", () => {
            updateEmailFeedback();
            updateEmailConfirmationFeedback();
        });
        emailInput.addEventListener("blur", updateEmailFeedback);
    }

    if (emailConfirmationInput) {
        emailConfirmationInput.addEventListener("input", updateEmailConfirmationFeedback);
        emailConfirmationInput.addEventListener("blur", updateEmailConfirmationFeedback);
    }

    if (passwordConfirmationInput) {
        passwordConfirmationInput.addEventListener("input", updatePasswordConfirmationFeedback);
    }

    signupForm.addEventListener("submit", (event) => {
        const validations = [
            { valid: updateCpfFeedback(), field: cpfInput },
            { valid: updateBirthDateFeedback(), field: birthDateInput },
            { valid: updateEmailFeedback(), field: emailInput },
            { valid: updateEmailConfirmationFeedback(), field: emailConfirmationInput },
            { valid: updateCepFormatFeedback(), field: cepInput },
        ];
        const firstInvalid = validations.find((item) => !item.valid);

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.field?.focus();
        }
    });

    if (!cepInput || !streetField || !districtField || !cityField || !stateField) {
        return;
    }

    let lastFetchedCep = "";

        const applyCepLookup = async () => {
            const cep = onlyDigits(cepInput.value);
            cepInput.value = formatCep(cepInput.value);

            if (cep.length !== 8 || cep === lastFetchedCep) {
                if (cep.length < 8) {
                    setCepStatus("");
                }
                return;
            }

        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);

            if (!response.ok) {
                throw new Error("Falha na consulta");
            }

            const data = await response.json();

            if (data.erro) {
                throw new Error("CEP nao encontrado");
            }

            streetField.value = data.logradouro || "";
            districtField.value = data.bairro || "";
            cityField.value = data.localidade || "";
            stateField.value = data.uf || "";
            lastFetchedCep = cep;
            setCepStatus("");
        } catch (error) {
            lastFetchedCep = "";
            setCepStatus("CEP incorreto.", "is-error");
        }
    };

    cepInput.addEventListener("input", () => {
        cepInput.value = formatCep(cepInput.value);

            if (onlyDigits(cepInput.value).length < 8) {
                lastFetchedCep = "";
                updateCepFormatFeedback();
            }
        });

    cepInput.addEventListener("blur", applyCepLookup);
}

function initPhoneMasks() {
    const phoneInputs = document.querySelectorAll("[data-phone-mask]");

    if (!phoneInputs.length) {
        return;
    }

    const onlyDigits = (value) => value.replace(/\D+/g, "");

    const formatPhone = (value) => {
        const digits = onlyDigits(value).slice(0, 11);

        if (digits.length <= 2) {
            return digits;
        }

        if (digits.length <= 7) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
        }

        if (digits.length <= 10) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
        }

        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
    };

    phoneInputs.forEach((input) => {
        input.addEventListener("input", () => {
            input.value = formatPhone(input.value);
        });
    });
}

function initAddressForms() {
    const addressForms = document.querySelectorAll("[data-address-form]");

    if (!addressForms.length) {
        return;
    }

    const onlyDigits = (value) => value.replace(/\D+/g, "");

    const formatCep = (value) => {
        const digits = onlyDigits(value).slice(0, 8);

        if (digits.length <= 5) {
            return digits;
        }

        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    addressForms.forEach((form) => {
        const cepInput = form.querySelector("[data-cep-input]");
        const streetField = form.querySelector("[data-address-street]");
        const districtField = form.querySelector("[data-address-district]");
        const cityField = form.querySelector("[data-address-city]");
        const stateField = form.querySelector("[data-address-state]");
        const cepStatus = form.querySelector("[data-cep-status]");

        if (!cepInput || !streetField || !districtField || !cityField || !stateField || !cepStatus) {
            return;
        }

        let lastFetchedCep = "";

        const setCepStatus = (message, type = "") => {
            cepStatus.textContent = message;
            cepStatus.classList.remove("is-loading", "is-success", "is-error");

            if (type) {
                cepStatus.classList.add(type);
            }
        };

        const applyCepLookup = async () => {
            const cep = onlyDigits(cepInput.value);
            cepInput.value = formatCep(cepInput.value);

            if (cep.length !== 8 || cep === lastFetchedCep) {
                if (cep.length < 8) {
                    setCepStatus("");
                }
                return;
            }

            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);

                if (!response.ok) {
                    throw new Error("Falha na consulta");
                }

                const data = await response.json();

                if (data.erro) {
                    throw new Error("CEP nao encontrado");
                }

                streetField.value = data.logradouro || "";
                districtField.value = data.bairro || "";
                cityField.value = data.localidade || "";
                stateField.value = data.uf || "";
                lastFetchedCep = cep;
                setCepStatus("");
            } catch (error) {
                lastFetchedCep = "";
                setCepStatus("CEP incorreto.", "is-error");
            }
        };

        cepInput.addEventListener("input", () => {
            cepInput.value = formatCep(cepInput.value);

            if (onlyDigits(cepInput.value).length < 8) {
                lastFetchedCep = "";
                setCepStatus("");
            }
        });

        cepInput.addEventListener("blur", applyCepLookup);
    });
}
