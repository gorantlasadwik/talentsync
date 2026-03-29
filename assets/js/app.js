(function () {
    function initBlurText() {
        var nodes = document.querySelectorAll('[data-blur-text]');
        if (!nodes.length) {
            return;
        }

        nodes.forEach(function (node) {
            var text = node.getAttribute('data-blur-text') || '';
            var words = text.split(' ');
            node.innerHTML = '';

            words.forEach(function (word, index) {
                var span = document.createElement('span');
                span.className = 'blur-word';
                span.style.animationDelay = index * 100 + 'ms';
                span.textContent = word + (index < words.length - 1 ? ' ' : '');
                node.appendChild(span);
            });

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.querySelectorAll('.blur-word').forEach(function (wordEl) {
                            wordEl.classList.add('in');
                        });
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.2 });

            observer.observe(node);
        });
    }

    function initHlsVideos() {
        var videos = document.querySelectorAll('[data-hls]');
        if (!videos.length) {
            return;
        }

        videos.forEach(function (video) {
            var src = video.getAttribute('data-hls');
            if (!src) {
                return;
            }

            if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = src;
                return;
            }

            if (window.Hls && Hls.isSupported()) {
                var hls = new Hls();
                hls.loadSource(src);
                hls.attachMedia(video);
            }
        });
    }

    function initGeoCapture() {
        var btn = document.getElementById('captureLocationBtn');
        if (!btn) {
            return;
        }

        function pickBestCity(address) {
            if (!address) {
                return '';
            }
            return address.city || address.town || address.village || address.county || address.state_district || address.state || '';
        }

        function setLocationHint(message, isError) {
            var hint = document.getElementById('locationHint');
            if (!hint) {
                return;
            }
            hint.textContent = message;
            hint.style.color = isError ? 'rgba(255, 148, 148, 0.95)' : 'rgba(255, 255, 255, 0.62)';
        }

        btn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported in this browser.');
                return;
            }

            setLocationHint('Getting your coordinates and place...', false);

            navigator.geolocation.getCurrentPosition(function (pos) {
                var lat = document.getElementById('lat');
                var lng = document.getElementById('lng');
                var city = document.getElementById('city');
                if (lat && lng) {
                    lat.value = pos.coords.latitude.toFixed(6);
                    lng.value = pos.coords.longitude.toFixed(6);
                }

                var reverseUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' +
                    encodeURIComponent(pos.coords.latitude) + '&lon=' + encodeURIComponent(pos.coords.longitude);

                fetch(reverseUrl, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('Reverse geocoding failed');
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        var cityName = pickBestCity(data.address);
                        if (city) {
                            city.value = cityName || data.display_name || '';
                        }
                        setLocationHint('Coordinates and place captured. You can edit the city/place if needed.', false);
                    })
                    .catch(function () {
                        setLocationHint('Coordinates captured. Place lookup failed, so enter city manually.', true);
                    });
            }, function () {
                setLocationHint('Location access denied or unavailable on this device.', true);
            });
        });
    }

    initBlurText();
    initHlsVideos();
    initGeoCapture();
})();
