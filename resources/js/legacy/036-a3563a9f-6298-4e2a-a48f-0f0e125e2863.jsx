const React = window.React;

/* Builder360 — safe fallback for retired config-driven legacy module screens. */
(function () {
  const { Icon, Badge, Button, Card, PageHead, Empty } = window;
  const e = React.createElement;

  function ModuleScreen({ cfg, toast }) {
    const title = cfg?.title || "Module unavailable";
    const crumbs = Array.isArray(cfg?.crumbs) ? cfg.crumbs : ["Builder360", "Module"];
    const sub = cfg?.sub || "This route requires a Laravel-backed module component. Static legacy module data is disabled.";
    const notify = () => {
      if (toast) {
        toast("This legacy fallback is disabled. Use the Laravel-backed module route.", "orange");
      }
    };

    return e("div", { className: "page page-wide" },
      e(PageHead, {
        crumbs,
        title,
        sub,
        actions: [
          e(Button, {
            key: "disabled-fallback",
            icon: "alert",
            onClick: notify,
            children: "Backend module required",
          }),
        ],
      }),
      e("div", { className: "hrx-warning", style: { marginBottom: 16 } },
        e(Icon, { name: "alert", size: 17 }),
        e("div", null,
          e("b", null, "Legacy static module disabled"),
          e("span", null, "Builder360 no longer renders hardcoded module rows, counters, salaries, vendors, documents, approvals or integration statuses from this fallback bundle.")),
        e(Badge, { tone: "b-orange" }, "SERVER REQUIRED")),
      e(Card, { title: "Laravel-backed screen required", sub: "No local fallback rows are fabricated." },
        e(Empty, {
          icon: "shield",
          title: "No static module data available",
          sub: "If this page appears, the route is missing its dedicated Laravel component mapping. Connect the route to a governed Laravel screen, API payload, authorization policy and tests before enabling it.",
        })),
    );
  }

  window.ModuleScreen = ModuleScreen;
  window.MODULE_CFG = {};
})();
