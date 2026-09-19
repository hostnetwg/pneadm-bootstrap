<script>
    window.ensureCourseSelectInit = window.ensureCourseSelectInit || function () {
        const fallbackScriptUrl = @json(asset('js/course-select-fallback.js'));

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                const existing = document.querySelector('script[data-course-select-fallback="1"]');
                if (existing) {
                    if (existing.dataset.loaded === '1') {
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', function () { resolve(); });
                    existing.addEventListener('error', function () { reject(new Error('Script load failed')); });
                    return;
                }
                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.dataset.courseSelectFallback = '1';
                script.onload = function () {
                    script.dataset.loaded = '1';
                    resolve();
                };
                script.onerror = function () {
                    reject(new Error('Script load failed'));
                };
                document.head.appendChild(script);
            });
        }

        function waitForInitFn(maxWaitMs) {
            return new Promise(function (resolve) {
                if (typeof window.initCourseSelect === 'function') {
                    resolve(window.initCourseSelect);
                    return;
                }
                const started = Date.now();
                const timer = window.setInterval(function () {
                    if (typeof window.initCourseSelect === 'function') {
                        window.clearInterval(timer);
                        resolve(window.initCourseSelect);
                        return;
                    }
                    if (Date.now() - started >= maxWaitMs) {
                        window.clearInterval(timer);
                        resolve(null);
                    }
                }, 50);
            });
        }

        return waitForInitFn(800).then(function (initFn) {
            if (initFn) {
                return initFn;
            }
            return loadScript(fallbackScriptUrl).then(function () {
                if (typeof window.ensureCourseSelectFallback !== 'function') {
                    return null;
                }
                return window.ensureCourseSelectFallback();
            });
        });
    };
</script>
