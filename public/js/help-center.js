(() => {
    const search = document.querySelector('[data-help-search]');

    if (!search) {
        return;
    }

    const articles = [...document.querySelectorAll('[data-help-article]')];
    const empty = document.querySelector('[data-help-empty]');

    const filterArticles = () => {
        const terms = search.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
        let visibleCount = 0;

        articles.forEach((article) => {
            const searchableText = article.dataset.helpSearchText ?? '';
            const visible = terms.every((term) => searchableText.includes(term));
            article.classList.toggle('d-none', !visible);
            document.querySelector(`[data-help-nav-link="${article.dataset.helpArticle}"]`)?.classList.toggle('d-none', !visible);
            visibleCount += visible ? 1 : 0;
        });

        document.querySelectorAll('[data-help-section]').forEach((section) => {
            section.classList.toggle('d-none', !section.querySelector('[data-help-article]:not(.d-none)'));
        });

        document.querySelectorAll('[data-help-nav-section]').forEach((section) => {
            section.classList.toggle('d-none', !section.querySelector('[data-help-nav-link]:not(.d-none)'));
        });

        empty?.classList.toggle('d-none', visibleCount !== 0);
    };

    search.addEventListener('input', filterArticles);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            search.value = '';
            filterArticles();
        }
    });
})();
