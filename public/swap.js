(function () {

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

    function handleSwap(el, method, url, formData) {
        const selector = el.getAttribute('data-swap');
        const target = document.querySelector(selector);
        if (!target) return;

        const spinner = `
            <svg width="57" height="57" viewBox="0 0 57 57" xmlns="http://www.w3.org/2000/svg">
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

        target.classList.add('loading');
        target.innerHTML = spinner;

        let fetchOptions = {
            method,
            headers: {
                'X-Swap-Call': 'true'
            }
        };

        if (method === 'GET' && formData) {
            const params = new URLSearchParams(formData).toString();
            url += (url.includes('?') ? '&' : '?') + params;
        } else if (method !== 'GET' && formData) {
            fetchOptions.body = formData;
        }

        fetch(url, fetchOptions)
            .then(resp => resp.text())
            .then(html => {
                target.classList.remove('loading');
                target.innerHTML = html;
            })
            .catch(err => {
                target.classList.remove('loading');
                target.innerHTML = 'Error loading content.';
                console.error(err);
            });
    }

})();
