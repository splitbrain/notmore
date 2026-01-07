/**
 * Lightweight swapper that intercepts links/forms marked with data-swap, fetches
 * their HTML via fetch, and replaces the target element while showing a spinner.
 * Optional data-swap-opts supports "toggle" to show/hide fetched content.
 */
(function () {

    const SPINNER = `
        <svg width="57" height="57" viewBox="0 0 57 57" xmlns="http://www.w3.org/2000/svg" class="swap-spinner">
            <g fill-rule="evenodd">
                <g transform="translate(1 1)" stroke-width="2">
                    <circle cx="5" cy="50" r="5">
                        <animate attributeName="cy"
                                 begin="0s" dur="2.2s"
                                 values="50;5;50;50"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                        <animate attributeName="cx"
                                 begin="0s" dur="2.2s"
                                 values="5;27;49;5"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                    </circle>
                    <circle cx="27" cy="5" r="5">
                        <animate attributeName="cy"
                                 begin="0s" dur="2.2s"
                                 from="5" to="5"
                                 values="5;50;50;5"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                        <animate attributeName="cx"
                                 begin="0s" dur="2.2s"
                                 from="27" to="27"
                                 values="27;49;5;27"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                    </circle>
                    <circle cx="49" cy="50" r="5">
                        <animate attributeName="cy"
                                 begin="0s" dur="2.2s"
                                 values="50;50;5;50"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                        <animate attributeName="cx"
                                 from="49" to="49"
                                 begin="0s" dur="2.2s"
                                 values="49;5;27;49"
                                 calcMode="linear"
                                 repeatCount="indefinite"/>
                    </circle>
                </g>
            </g>
        </svg>
    `;

    document.addEventListener('submit', function (ev) {
        const form = ev.target;
        if (!form.matches('form[data-swap]')) return;

        ev.preventDefault();

        const method = (form.method || 'GET').toUpperCase();
        const action = form.action;
        const data = new FormData(form);

        handleSwap(form, method, action, data);
    });

    document.addEventListener('click', function (ev) {
        const link = ev.target.closest('a[data-swap]');
        if (!link) return;

        ev.preventDefault();

        const method = 'GET';
        const href = link.href;

        handleSwap(link, method, href, null);
    });

    /**
     * Fetch HTML for a swap request and replace the configured target element.
     *
     * @param {HTMLElement} el Element that initiated the swap and carries the data-swap selector.
     * @param {string} method HTTP verb to use for the request (usually GET or POST).
     * @param {string} url Absolute URL to call for the swap content.
     * @param {FormData|null} formData Serialized form data when available; null for plain links.
     * data-swap-opts currently supports "toggle" to wrap or remove existing swap content.
     */
    function handleSwap(el, method, url, formData) {
        const { target, options } = resolveSwapConfig(el);
        if (!target) return;

        if (options.toggle && removeExistingToggle(target)) {
            return;
        }

        const { requestUrl, fetchOptions } = prepareRequest(method, url, formData);

        showLoading(target);

        fetch(requestUrl, fetchOptions)
            .then(resp => resp.text())
            .then(html => {
                renderResult(target, html);
            })
            .catch(err => {
                console.error(err);
                renderResult(target, 'Error loading content.');
            });
    }

    /**
     * Resolve swap configuration, returning target and parsed options.
     *
     * @param {HTMLElement} el Element carrying the data-swap and optional data-swap-opts attributes.
     * @returns {{target: Element|null, options: {toggle: boolean}}}
     */
    function resolveSwapConfig(el) {
        const selector = el.getAttribute('data-swap');
        const target = selector ? document.querySelector(selector) : null;
        const optsAttr = el.getAttribute('data-swap-opts') || '';
        const swapOpts = new Set(optsAttr.split(/\s+/).filter(Boolean));

        return {
            target,
            options: {
                toggle: swapOpts.has('toggle')
            }
        };
    }

    /**
     * Remove an existing toggle wrapper if present.
     *
     * @param {Element} target Element that may contain a toggle wrapper.
     * @returns {boolean} True when a wrapper was removed.
     */
    function removeExistingToggle(target) {
        const existingToggle = target.querySelector('div.swap-toggle');
        if (!existingToggle) return false;
        existingToggle.remove();
        return true;
    }

    /**
     * Prepare fetch parameters and mutate the URL for GET requests with form data.
     *
     * @param {string} method HTTP verb for the request.
     * @param {string} url Base URL to call.
     * @param {FormData|null} formData Form data, if available.
     * @returns {{requestUrl: string, fetchOptions: RequestInit}}
     */
    function prepareRequest(method, url, formData) {
        const fetchOptions = {
            method,
            headers: {
                'X-Swap-Call': 'true'
            }
        };

        let requestUrl = url;

        if (method === 'GET' && formData) {
            const params = new URLSearchParams(formData).toString();
            requestUrl += (requestUrl.includes('?') ? '&' : '?') + params;
        } else if (method !== 'GET' && formData) {
            fetchOptions.body = formData;
        }

        return { requestUrl, fetchOptions };
    }

    /**
     * Show loading state with spinner markup.
     *
     * @param {Element} target Element to update.
     */
    function showLoading(target) {
        target.classList.add('loading');
        target.innerHTML = SPINNER;
    }

    /**
     * Render swap content (success or error) into the target, always wrapped in a container.
     *
     * @param {Element} target Element to update.
     * @param {string} html HTML payload or error message.
     */
    function renderResult(target, html) {
        target.classList.remove('loading');

        const wrapper = document.createElement('div');
        wrapper.className = 'swap-toggle';
        wrapper.innerHTML = html;
        target.innerHTML = '';
        target.appendChild(wrapper);
    }

})();
