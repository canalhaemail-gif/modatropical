document.addEventListener("DOMContentLoaded", () => {
    const flashMessages = document.querySelectorAll(".flash");
    const imageInputs = document.querySelectorAll("[data-image-input]");
    const colorFields = document.querySelectorAll(".color-field");
    const cpfInput = document.querySelector("#cpf");
    const phoneInput = document.querySelector("#telefone");
    const cepInput = document.querySelector("#cep");
    const menuToggle = document.querySelector("[data-admin-menu-toggle]");
    const menuClose = document.querySelector("[data-admin-menu-close]");
    const menuBackdrop = document.querySelector("[data-admin-backdrop]");
    const menuLinks = document.querySelectorAll("[data-admin-nav-link]");
    const body = document.body;

    initAdminOrdersAutoRefresh();
    initCouponFormVisibility();
    initProductFormVisibility();

    const onlyDigits = (value) => String(value || "").replace(/\D+/g, "");

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

    const formatCep = (value) => {
        const digits = onlyDigits(value).slice(0, 8);

        if (digits.length <= 5) {
            return digits;
        }

        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    const setMenuState = (open) => {
        body.classList.toggle("admin-menu-open", open);

        if (menuToggle) {
            menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
        }
    };

    flashMessages.forEach((flash) => {
        window.setTimeout(() => {
            flash.style.opacity = "0";
            flash.style.transform = "translateY(-6px)";
            flash.style.transition = "opacity 0.3s ease, transform 0.3s ease";

            window.setTimeout(() => flash.remove(), 320);
        }, 3600);
    });

    const syncProductPreviewHeights = () => {
        const mainPreview = document.querySelector("#produto-preview");
        const galleryPreview = document.querySelector("#produto-galeria-preview");

        if (!mainPreview || !galleryPreview) {
            return;
        }

        const mainRect = mainPreview.getBoundingClientRect();
        const mainHeight = Math.max(0, Math.round(mainRect.height));

        if (mainHeight <= 0) {
            return;
        }

        galleryPreview.style.setProperty("--product-gallery-empty-height", `${mainHeight}px`);
        galleryPreview.style.setProperty("--product-gallery-max-height", `${mainHeight}px`);
    };

    const requestPreviewHeightSync = () => {
        window.requestAnimationFrame(syncProductPreviewHeights);
    };

    const previewModal = (() => {
        const modal = document.createElement("div");
        modal.className = "admin-image-modal";
        modal.hidden = true;
        modal.innerHTML = `
            <div class="admin-image-modal__backdrop" data-admin-image-modal-close></div>
            <div class="admin-image-modal__dialog" role="dialog" aria-modal="true" aria-label="Visualizacao da imagem">
                <button class="admin-image-modal__close" type="button" aria-label="Fechar visualizacao" data-admin-image-modal-close>x</button>
                <img class="admin-image-modal__image" alt="">
            </div>
        `;

        document.body.appendChild(modal);

        const image = modal.querySelector(".admin-image-modal__image");
        const closeModal = () => {
            modal.hidden = true;
            document.body.classList.remove("admin-image-modal-open");
            if (image) {
                image.removeAttribute("src");
                image.removeAttribute("alt");
            }
        };

        modal.addEventListener("click", (event) => {
            if (event.target instanceof HTMLElement && event.target.closest("[data-admin-image-modal-close]")) {
                closeModal();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !modal.hidden) {
                closeModal();
            }
        });

        return {
            open(src, alt) {
                if (!image) {
                    return;
                }

                image.src = src;
                image.alt = alt || "Preview da imagem";
                modal.hidden = false;
                document.body.classList.add("admin-image-modal-open");
            },
            close: closeModal,
        };
    })();

    const attachStaticPreviewImage = (target) => {
        target.querySelectorAll("img").forEach((image) => {
            if (image.dataset.previewBound === "1") {
                return;
            }

            image.dataset.previewBound = "1";
            image.classList.add("image-preview__clickable-image");

            if (!image.complete) {
                image.addEventListener("load", requestPreviewHeightSync, { once: true });
            } else {
                requestPreviewHeightSync();
            }

            image.addEventListener("click", () => {
                previewModal.open(image.currentSrc || image.src, image.alt || "Preview da imagem");
            });
        });
    };

    if (typeof ResizeObserver !== "undefined") {
        const mainPreview = document.querySelector("#produto-preview");

        if (mainPreview) {
            const previewResizeObserver = new ResizeObserver(() => {
                requestPreviewHeightSync();
            });

            previewResizeObserver.observe(mainPreview);
        }
    }

    window.addEventListener("resize", requestPreviewHeightSync);
    requestPreviewHeightSync();

    imageInputs.forEach((input) => {
        const target = document.querySelector(input.dataset.imageInput || "");
        const fileNameTarget = input
            .closest(".admin-file-field")
            ?.querySelector(input.dataset.fileNameTarget || "");
        const defaultHtml = target ? target.innerHTML : "";
        const defaultFileNameText = fileNameTarget ? fileNameTarget.textContent || "" : "";
        let objectUrls = [];

        const clearObjectUrls = () => {
            objectUrls.forEach((url) => URL.revokeObjectURL(url));
            objectUrls = [];
        };

        const rememberObjectUrl = (url) => {
            objectUrls.push(url);
            return url;
        };

        const setFileLabel = (files) => {
            if (!fileNameTarget) {
                return;
            }

            if (input.hasAttribute("multiple")) {
                fileNameTarget.textContent = files.length > 0
                    ? `${files.length} imagem(ns) selecionada(s)`
                    : defaultFileNameText;
                return;
            }

            fileNameTarget.textContent = files[0]?.name || defaultFileNameText;
        };

        const restoreDefaultPreview = () => {
            if (!target) {
                return;
            }

            clearObjectUrls();
            target.classList.remove("has-grid", "has-image");
            target.innerHTML = defaultHtml;
            setFileLabel([]);
            attachStaticPreviewImage(target);
            syncProductPreviewHeights();
        };

        const updateInputFiles = (nextFiles) => {
            const dataTransfer = new DataTransfer();
            nextFiles.forEach((nextFile) => dataTransfer.items.add(nextFile));
            input.files = dataTransfer.files;
        };

        const buildPreviewButton = (src, alt, modifierClass) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = `image-preview__media-button ${modifierClass}`.trim();
            button.setAttribute("aria-label", `Ampliar ${alt}`);
            button.addEventListener("click", () => {
                previewModal.open(src, alt);
            });

            const image = document.createElement("img");
            image.src = src;
            image.alt = alt;
            image.addEventListener("load", requestPreviewHeightSync, { once: true });
            button.appendChild(image);

            return button;
        };

        const buildRemoveButton = (label, onClick) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "image-preview__remove";
            button.setAttribute("aria-label", label);
            button.textContent = "x";
            button.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                onClick();
            });

            return button;
        };

        const renderSinglePreview = (file) => {
            if (!target) {
                return;
            }

            if (!file) {
                restoreDefaultPreview();
                return;
            }

            clearObjectUrls();
            target.classList.remove("has-grid");
            target.classList.add("has-image");
            target.innerHTML = "";

            const wrapper = document.createElement("div");
            wrapper.className = "image-preview__tile image-preview__tile--single";

            const src = rememberObjectUrl(URL.createObjectURL(file));
            wrapper.appendChild(buildPreviewButton(src, file.name, "image-preview__media-button--single"));
            wrapper.appendChild(buildRemoveButton("Remover imagem principal selecionada", () => {
                input.value = "";
                restoreDefaultPreview();
            }));

            target.appendChild(wrapper);
            setFileLabel([file]);
            requestPreviewHeightSync();
        };

        const renderGalleryPreview = (files) => {
            if (!target) {
                return;
            }

            clearObjectUrls();
            target.innerHTML = "";

            if (files.length === 0) {
                target.classList.remove("has-grid", "has-image");
                target.innerHTML = "<span>Nenhuma imagem extra selecionada.</span>";
                setFileLabel([]);
                requestPreviewHeightSync();
                return;
            }

            target.classList.add("has-grid", "has-image");
            const grid = document.createElement("div");
            grid.className = "gallery-preview-grid";
            const columns = files.length <= 1 ? 1 : files.length <= 2 ? 2 : files.length <= 4 ? 2 : 3;
            const rows = Math.ceil(files.length / columns);
            grid.style.setProperty("--gallery-preview-columns", String(columns));
            grid.style.setProperty("--gallery-preview-rows", String(rows));

            files.forEach((currentFile, index) => {
                const item = document.createElement("div");
                item.className = "image-preview__tile image-preview__tile--gallery";

                const src = rememberObjectUrl(URL.createObjectURL(currentFile));
                item.appendChild(buildPreviewButton(src, currentFile.name, "image-preview__media-button--gallery"));
                item.appendChild(buildRemoveButton(`Remover ${currentFile.name}`, () => {
                    const nextFiles = Array.from(input.files || []).filter((_, itemIndex) => itemIndex !== index);
                    updateInputFiles(nextFiles);
                    renderGalleryPreview(nextFiles);
                }));
                grid.appendChild(item);
            });

            target.appendChild(grid);
            setFileLabel(files);
            requestPreviewHeightSync();
        };

        if (target) {
            attachStaticPreviewImage(target);
        }

        input.addEventListener("change", (event) => {
            const files = Array.from(event.target.files || []);
            const file = files[0];

            if (!target) {
                return;
            }

            if (input.hasAttribute("multiple")) {
                renderGalleryPreview(files);
                return;
            }

            renderSinglePreview(file);
        });
    });

    document.querySelectorAll("[data-flavor-builder]").forEach((builder) => {
        const input = builder.querySelector("[data-flavor-input]");
        const stockInput = builder.querySelector("[data-flavor-stock-input]");
        const addButton = builder.querySelector("[data-flavor-add]");
        const list = builder.querySelector("[data-flavor-list]");
        const storage = builder.querySelector("[data-flavor-storage]");

        if (!input || !stockInput || !addButton || !list || !storage) {
            return;
        }

        const parseStorage = () => {
            const raw = String(storage.value || "").trim();

            if (raw === "") {
                return [];
            }

            try {
                const decoded = JSON.parse(raw);

                if (Array.isArray(decoded)) {
                    return decoded
                        .filter((item) => item && typeof item === "object")
                        .map((item) => ({
                            name: String(item.nome || "").trim(),
                            stock: Math.max(0, Number.parseInt(item.estoque ?? 0, 10) || 0),
                        }))
                        .filter((item) => item.name !== "");
                }
            } catch (error) {
                // Fallback para formato legado.
            }

            return raw
                .split(/\r?\n|,|;/)
                .map((value) => String(value || "").trim())
                .filter((value) => value !== "")
                .map((value) => {
                    const [name, stockValue] = value.split("|");

                    return {
                        name: String(name || "").trim(),
                        stock: Math.max(0, Number.parseInt(String(stockValue || "0").trim(), 10) || 0),
                    };
                })
                .filter((item) => item.name !== "");
        };

        let flavors = parseStorage();

        const syncStorage = () => {
            storage.value = JSON.stringify(
                flavors.map((flavor) => ({
                    nome: flavor.name,
                    estoque: Math.max(0, Number.parseInt(flavor.stock, 10) || 0),
                }))
            );
        };

        const render = () => {
            list.innerHTML = "";

            flavors.forEach((flavor, index) => {
                const item = document.createElement("div");
                item.className = "product-flavor-builder__item";

                const label = document.createElement("span");
                label.className = "product-flavor-builder__item-name";
                label.textContent = flavor.name;

                const quantity = document.createElement("input");
                quantity.className = "product-flavor-builder__item-stock";
                quantity.type = "number";
                quantity.min = "0";
                quantity.value = String(Math.max(0, Number.parseInt(flavor.stock, 10) || 0));
                quantity.setAttribute("aria-label", `Quantidade do tamanho ${flavor.name}`);
                quantity.addEventListener("input", () => {
                    flavors[index].stock = Math.max(0, Number.parseInt(quantity.value || "0", 10) || 0);
                    syncStorage();
                });

                const removeButton = document.createElement("button");
                removeButton.type = "button";
                removeButton.className = "product-flavor-builder__item-remove";
                removeButton.setAttribute("aria-label", `Remover tamanho ${flavor.name}`);
                removeButton.textContent = "x";
                removeButton.textContent = "×";
                removeButton.textContent = "x";
                removeButton.addEventListener("click", () => {
                    flavors.splice(index, 1);
                    syncStorage();
                    render();
                });

                item.appendChild(label);
                item.appendChild(quantity);
                item.appendChild(removeButton);
                list.appendChild(item);
            });
        };

        const addFlavor = () => {
            const value = String(input.value || "").trim();
            const stock = Math.max(0, Number.parseInt(String(stockInput.value || "0").trim(), 10) || 0);

            if (value === "") {
                input.value = "";
                return;
            }

            const existingIndex = flavors.findIndex(
                (flavor) => flavor.name.toLowerCase() === value.toLowerCase()
            );

            if (existingIndex >= 0) {
                flavors[existingIndex].stock = stock;
            } else {
                flavors.push({
                    name: value,
                    stock,
                });
            }

            syncStorage();
            render();
            input.value = "";
            stockInput.value = "";
            input.focus();
        };

        addButton.addEventListener("click", addFlavor);
        input.addEventListener("change", () => {
            if (String(input.value || "").trim() !== "") {
                stockInput.focus();
            }
        });
        [input, stockInput].forEach((field) => field.addEventListener("keydown", (event) => {
            if (event.key !== "Enter") {
                return;
            }

            event.preventDefault();
            addFlavor();
        }));

        syncStorage();
        render();
    });

    document.querySelectorAll("[data-flavor-builder]").forEach((builder) => {
        const selectionInputs = Array.from(builder.querySelectorAll("[data-flavor-input]"));
        const addButton = builder.querySelector("[data-flavor-add]");
        const list = builder.querySelector("[data-flavor-list]");
        const storage = builder.querySelector("[data-flavor-storage]");
        const hasBatchSelection = builder.querySelector(".product-flavor-builder__size-grid");

        if (!hasBatchSelection || selectionInputs.length === 0 || !addButton || !list || !storage) {
            return;
        }

        const normalizeFlavorName = (value) => String(value || "").trim();

        const sortFlavors = (items) => (
            [...items].sort((left, right) => {
                const leftValue = Number.parseInt(left.name, 10);
                const rightValue = Number.parseInt(right.name, 10);

                if (!Number.isNaN(leftValue) && !Number.isNaN(rightValue)) {
                    return leftValue - rightValue;
                }

                return left.name.localeCompare(right.name, "pt-BR", { numeric: true });
            })
        );

        const parseStorage = () => {
            const raw = String(storage.value || "").trim();

            if (raw === "") {
                return [];
            }

            try {
                const decoded = JSON.parse(raw);

                if (Array.isArray(decoded)) {
                    return sortFlavors(
                        decoded
                            .filter((item) => item && typeof item === "object")
                            .map((item) => ({
                                name: normalizeFlavorName(item.nome || ""),
                                stock: Math.max(0, Number.parseInt(item.estoque ?? 0, 10) || 0),
                            }))
                            .filter((item) => item.name !== "")
                    );
                }
            } catch (error) {
                // Fallback para formato legado.
            }

            return sortFlavors(
                raw
                    .split(/\r?\n|,|;/)
                    .map((value) => String(value || "").trim())
                    .filter((value) => value !== "")
                    .map((value) => {
                        const [name, stockValue] = value.split("|");

                        return {
                            name: normalizeFlavorName(name || ""),
                            stock: Math.max(0, Number.parseInt(String(stockValue || "0").trim(), 10) || 0),
                        };
                    })
                    .filter((item) => item.name !== "")
            );
        };

        let flavors = parseStorage();

        const syncStorage = () => {
            storage.value = JSON.stringify(
                sortFlavors(flavors).map((flavor) => ({
                    nome: flavor.name,
                    estoque: Math.max(0, Number.parseInt(flavor.stock, 10) || 0),
                }))
            );
        };

        const render = () => {
            list.innerHTML = "";

            if (flavors.length === 0) {
                const empty = document.createElement("div");
                empty.className = "inline-empty-state";
                empty.innerHTML = "<span>Nenhum tamanho selecionado ainda.</span>";
                list.appendChild(empty);
                return;
            }

            sortFlavors(flavors).forEach((flavor) => {
                const item = document.createElement("div");
                item.className = "product-flavor-builder__item";

                const label = document.createElement("span");
                label.className = "product-flavor-builder__item-name";
                label.textContent = flavor.name;

                const quantity = document.createElement("input");
                quantity.className = "product-flavor-builder__item-stock";
                quantity.type = "number";
                quantity.min = "0";
                quantity.value = String(Math.max(0, Number.parseInt(flavor.stock, 10) || 0));
                quantity.setAttribute("aria-label", `Quantidade do tamanho ${flavor.name}`);
                quantity.addEventListener("input", () => {
                    flavor.stock = Math.max(0, Number.parseInt(quantity.value || "0", 10) || 0);
                    syncStorage();
                });

                const removeButton = document.createElement("button");
                removeButton.type = "button";
                removeButton.className = "product-flavor-builder__item-remove";
                removeButton.setAttribute("aria-label", `Remover tamanho ${flavor.name}`);
                removeButton.textContent = "x";
                removeButton.addEventListener("click", () => {
                    flavors = flavors.filter((itemEntry) => itemEntry.name !== flavor.name);
                    syncStorage();
                    render();
                });

                item.appendChild(label);
                item.appendChild(quantity);
                item.appendChild(removeButton);
                list.appendChild(item);
            });
        };

        const addFlavors = () => {
            const selectedValues = selectionInputs
                .filter((input) => input.checked)
                .map((input) => normalizeFlavorName(input.value))
                .filter((value) => value !== "");

            if (selectedValues.length === 0) {
                return;
            }

            selectedValues.forEach((value) => {
                const exists = flavors.some((flavor) => flavor.name.toLowerCase() === value.toLowerCase());

                if (!exists) {
                    flavors.push({
                        name: value,
                        stock: 0,
                    });
                }
            });

            flavors = sortFlavors(flavors);
            syncStorage();
            render();
            selectionInputs.forEach((input) => {
                input.checked = false;
            });
        };

        addButton.addEventListener("click", addFlavors);
        selectionInputs.forEach((input) => {
            input.addEventListener("keydown", (event) => {
                if (event.key !== "Enter") {
                    return;
                }

                event.preventDefault();
                addFlavors();
            });
        });

        syncStorage();
        render();
    });

    colorFields.forEach((field) => {
        const colorInput = field.querySelector('input[type="color"]');
        const textInput = field.querySelector('input[readonly]');

        if (!colorInput || !textInput) {
            return;
        }

        colorInput.addEventListener("input", () => {
            textInput.value = colorInput.value;
        });
    });

    if (cpfInput) {
        cpfInput.addEventListener("input", () => {
            cpfInput.value = formatCpf(cpfInput.value);
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener("input", () => {
            phoneInput.value = formatPhone(phoneInput.value);
        });
    }

    if (cepInput) {
        cepInput.addEventListener("input", () => {
            cepInput.value = formatCep(cepInput.value);
        });
    }

    if (menuToggle) {
        menuToggle.addEventListener("click", () => {
            setMenuState(!body.classList.contains("admin-menu-open"));
        });
    }

    if (menuClose) {
        menuClose.addEventListener("click", () => setMenuState(false));
    }

    if (menuBackdrop) {
        menuBackdrop.addEventListener("click", () => setMenuState(false));
    }

    menuLinks.forEach((link) => {
        link.addEventListener("click", () => {
            if (window.innerWidth < 1024) {
                setMenuState(false);
            }
        });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            setMenuState(false);
        }
    });

    document.querySelectorAll(".admin-table").forEach((table) => {
        const headers = Array.from(table.querySelectorAll("thead th")).map((header) =>
            header.textContent?.trim() || ""
        );

        table.querySelectorAll("tbody tr").forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (!cell.hasAttribute("data-label") && headers[index]) {
                    cell.setAttribute("data-label", headers[index]);
                }
            });
        });
    });

    document.querySelectorAll("[data-optional-field-toggle]").forEach((toggle) => {
        const targetSelector = toggle.getAttribute("data-optional-field-toggle") || "";
        const inputSelector = toggle.getAttribute("data-optional-field-input") || "";
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        const input = inputSelector ? document.querySelector(inputSelector) : null;
        const openLabel = toggle.getAttribute("data-label-open") || "Adicionar";
        const closeLabel = toggle.getAttribute("data-label-close") || "Remover";
        const clearOnHide = toggle.getAttribute("data-clear-on-hide") === "true";

        if (!target) {
            return;
        }

        const syncToggleState = () => {
            const isOpen = !target.hasAttribute("hidden");
            toggle.textContent = isOpen ? closeLabel : openLabel;
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        };

        toggle.addEventListener("click", () => {
            const isOpen = !target.hasAttribute("hidden");

            if (isOpen) {
                target.setAttribute("hidden", "");

                if (clearOnHide && input) {
                    input.value = "";
                }
            } else {
                target.removeAttribute("hidden");

                if (input) {
                    input.focus();
                }
            }

            syncToggleState();
        });

        syncToggleState();
    });
});

function initProductFormVisibility() {
    const activeToggle = document.querySelector("[data-product-active-toggle]");
    const featureToggle = document.querySelector("[data-product-feature-toggle]");
    const promoToggle = document.querySelector("[data-product-promo-toggle]");

    if (!activeToggle || !featureToggle || !promoToggle) {
        return;
    }

    const ensureActiveForExposure = () => {
        if (featureToggle.checked || promoToggle.checked) {
            activeToggle.checked = true;
        }
    };

    featureToggle.addEventListener("change", ensureActiveForExposure);
    promoToggle.addEventListener("change", ensureActiveForExposure);

    activeToggle.addEventListener("change", () => {
        if (activeToggle.checked) {
            return;
        }

        featureToggle.checked = false;
        promoToggle.checked = false;
    });

    ensureActiveForExposure();
}

function initCouponFormVisibility() {
    const typeField = document.querySelector("#tipo");
    const scopeField = document.querySelector("#escopo");

    if (!typeField && !scopeField) {
        return;
    }

    const discountRow = document.querySelector("#coupon-discount-row");
    const subtotalRow = document.querySelector("#coupon-subtotal-row");
    const productsRow = document.querySelector("#coupon-products-row");
    const brandsRow = document.querySelector("#coupon-brands-row");
    const discountLabel = document.querySelector('label[for="valor_desconto"]');
    const discountInput = document.querySelector("#valor_desconto");
    const discountHint = document.querySelector("#coupon-discount-hint");
    const subtotalHint = document.querySelector("#coupon-subtotal-hint");

    const setHidden = (element, hidden) => {
        if (!element) {
            return;
        }

        if (hidden) {
            element.setAttribute("hidden", "");
        } else {
            element.removeAttribute("hidden");
        }
    };

    const syncType = () => {
        const type = typeField ? String(typeField.value || "").trim() : "";
        const hasType = type !== "";
        const showDiscount = hasType && type !== "free_shipping";

        setHidden(discountRow, !showDiscount);
        setHidden(subtotalRow, !hasType);

        if (discountLabel) {
            discountLabel.textContent = type === "percent"
                ? "Valor do desconto (%)"
                : "Valor do desconto";
        }

        if (discountInput) {
            discountInput.placeholder = type === "percent" ? "Ex.: 10" : "Ex.: 10,00";
        }

        if (discountHint) {
            discountHint.textContent = type === "percent"
                ? "Para percentual, use 10 para 10%."
                : type === "above_value"
                    ? "Informe o desconto aplicado quando o subtotal minimo for atingido."
                    : "Informe o valor total do desconto. Ex.: 10,00.";
        }

        if (subtotalHint) {
            subtotalHint.textContent = type === "above_value"
                ? "Esse tipo exige um subtotal minimo para liberar o desconto."
                : type === "free_shipping"
                    ? "Use se quiser liberar frete gratis apenas acima de um valor minimo."
                    : "Use se quiser exigir um valor minimo para liberar o cupom.";
        }
    };

    const syncScope = () => {
        const scope = scopeField ? String(scopeField.value || "").trim() : "";

        setHidden(productsRow, scope !== "products");
        setHidden(brandsRow, scope !== "brands");
    };

    typeField?.addEventListener("change", syncType);
    scopeField?.addEventListener("change", syncScope);

    syncType();
    syncScope();
}

function initAdminOrdersAutoRefresh() {
    let liveRoot = document.querySelector("[data-admin-orders-live-root]");

    if (!liveRoot) {
        return;
    }

    const refreshUrl = liveRoot.dataset.adminOrdersLiveUrl || window.location.href;
    const pollInterval = Number.parseInt(liveRoot.dataset.adminOrdersLivePoll || "5000", 10);

    if (!refreshUrl || Number.isNaN(pollInterval) || pollInterval < 1000) {
        return;
    }

    let isRefreshing = false;

    const refreshOrders = async () => {
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
            const nextRoot = doc.querySelector("[data-admin-orders-live-root]");

            if (!nextRoot) {
                return;
            }

            const currentSignature = liveRoot.dataset.adminOrdersLiveSignature || "";
            const nextSignature = nextRoot.dataset.adminOrdersLiveSignature || "";

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

    window.setInterval(refreshOrders, pollInterval);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            refreshOrders();
        }
    });
}
