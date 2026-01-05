(function () {
    document.addEventListener('click', function (ev) {
        if (ev.target.tagName !== 'BLOCKQUOTE') return;
        ev.target.classList.toggle('full');
    });
})();
