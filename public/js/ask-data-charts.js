(() => {
    const question = document.querySelector('[data-ask-data-question]');

    document.querySelectorAll('[data-ask-data-example]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!question) return;
            question.value = button.dataset.askDataExample || '';
            question.focus();
        });
    });

    const canvas = document.querySelector('[data-ask-data-chart]');
    const config = window.askDataChartConfig;

    if (!canvas || !config || !config.labels?.length) return;

    const legend = document.querySelector('[data-ask-data-legend]');
    config.datasets.forEach((dataset) => {
        const item = document.createElement('span');
        item.className = 'd-inline-flex align-items-center gap-1 small';
        const swatch = document.createElement('span');
        swatch.className = 'd-inline-block rounded-circle';
        swatch.style.width = '10px';
        swatch.style.height = '10px';
        swatch.style.backgroundColor = dataset.color;
        item.append(swatch, document.createTextNode(dataset.label));
        legend?.append(item);
    });

    const formatValue = (value) => new Intl.NumberFormat(undefined, {
        maximumFractionDigits: Math.abs(value) < 100 ? 2 : 0,
    }).format(value);

    const render = () => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const width = Math.max(canvas.parentElement.clientWidth, 320);
        const height = 340;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        const styles = getComputedStyle(document.body);
        const textColor = styles.color || '#182433';
        const gridColor = textColor.startsWith('rgb') ? textColor.replace('rgb(', 'rgba(').replace(')', ', 0.12)') : 'rgba(98, 105, 118, 0.18)';
        const padding = { top: 18, right: 18, bottom: config.labels.length > 7 ? 82 : 58, left: 64 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const values = config.datasets.flatMap((dataset) => dataset.data.map(Number));
        const minimum = Math.min(0, ...values);
        const maximum = Math.max(0, ...values);
        const range = maximum - minimum || 1;
        const y = (value) => padding.top + ((maximum - value) / range) * plotHeight;
        const zeroY = y(0);

        context.clearRect(0, 0, width, height);
        context.font = '12px system-ui, sans-serif';
        context.fillStyle = textColor;
        context.strokeStyle = gridColor;
        context.lineWidth = 1;

        for (let step = 0; step <= 5; step += 1) {
            const value = maximum - (range * step / 5);
            const gridY = padding.top + (plotHeight * step / 5);
            context.beginPath();
            context.moveTo(padding.left, gridY);
            context.lineTo(width - padding.right, gridY);
            context.stroke();
            context.textAlign = 'right';
            context.textBaseline = 'middle';
            context.fillText(formatValue(value), padding.left - 9, gridY);
        }

        const slot = plotWidth / Math.max(config.labels.length, 1);
        context.textAlign = config.labels.length > 7 ? 'right' : 'center';
        context.textBaseline = 'top';
        config.labels.forEach((label, index) => {
            const x = padding.left + slot * (index + 0.5);
            context.save();
            context.translate(x, height - padding.bottom + 10);
            if (config.labels.length > 7) context.rotate(-Math.PI / 4);
            const shortLabel = String(label).length > 22 ? `${String(label).slice(0, 20)}…` : String(label);
            context.fillText(shortLabel, 0, 0);
            context.restore();
        });

        if (config.type === 'line') {
            config.datasets.forEach((dataset) => {
                context.strokeStyle = dataset.color;
                context.fillStyle = dataset.color;
                context.lineWidth = 2.5;
                context.beginPath();
                dataset.data.forEach((rawValue, index) => {
                    const x = padding.left + slot * (index + 0.5);
                    const pointY = y(Number(rawValue));
                    if (index === 0) context.moveTo(x, pointY);
                    else context.lineTo(x, pointY);
                });
                context.stroke();
                dataset.data.forEach((rawValue, index) => {
                    const x = padding.left + slot * (index + 0.5);
                    const pointY = y(Number(rawValue));
                    context.beginPath();
                    context.arc(x, pointY, 3.5, 0, Math.PI * 2);
                    context.fill();
                });
            });
            return;
        }

        const groupWidth = slot * 0.72;
        const barWidth = Math.max(groupWidth / Math.max(config.datasets.length, 1), 2);
        config.datasets.forEach((dataset, datasetIndex) => {
            context.fillStyle = dataset.color;
            dataset.data.forEach((rawValue, index) => {
                const value = Number(rawValue);
                const barX = padding.left + slot * index + (slot - groupWidth) / 2 + barWidth * datasetIndex;
                const valueY = y(value);
                context.fillRect(barX, Math.min(valueY, zeroY), Math.max(barWidth - 2, 1), Math.max(Math.abs(zeroY - valueY), 1));
            });
        });
    };

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(render, 120);
    });
    render();
})();
