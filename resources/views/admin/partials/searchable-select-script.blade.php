{{--
    Behaviour for admin.partials.searchable-select. Include once per page that
    renders one or more comboboxes, after the markup.
--}}
<script>
    // Dependency-free searchable combobox: type to filter, click to pick.
    // Backs a hidden input so the surrounding <form> submits a plain id.
    document.querySelectorAll('.searchable-select').forEach(function (wrapper) {
        const input  = wrapper.querySelector('[data-search-input]');
        const hidden = wrapper.querySelector('[data-hidden-input]');
        const list   = wrapper.querySelector('[data-options-list]');
        const options = Array.from(list.children);

        function showList() { list.classList.remove('hidden'); }
        function hideList() { list.classList.add('hidden'); }

        function filterOptions() {
            const term = input.value.trim().toLowerCase();
            options.forEach(function (opt) {
                const isEmptyOption = opt.dataset.value === '';
                const label = (opt.dataset.label || '').toLowerCase();
                opt.classList.toggle('hidden', !isEmptyOption && !label.includes(term));
            });
        }

        input.addEventListener('focus', function () { filterOptions(); showList(); });
        input.addEventListener('input', function () { filterOptions(); showList(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { hideList(); input.blur(); }
        });

        // Revert to the last confirmed selection if the field is left
        // without picking an option from the filtered list.
        input.addEventListener('blur', function () {
            setTimeout(function () {
                const match = options.find(o => o.dataset.value === hidden.value);
                input.value = match ? (match.dataset.label || '') : '';
                hideList();
            }, 150);
        });

        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                hidden.value = opt.dataset.value;
                input.value = opt.dataset.label || '';
                hideList();
            });
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) hideList();
        });
    });
</script>
