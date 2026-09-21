---
title: "FAQ"
nav_order: 11
description: "Short answers about darvis/livewire-injection-stopper: what it blocks, which versions it supports, what it cannot stop, and how it relates to Sentry."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
