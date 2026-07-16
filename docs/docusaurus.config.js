// @ts-check

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'LazyMePHP',
  tagline: 'The database schema is the application.',
  favicon: 'img/favicon.ico',

  url: 'https://peixinho.github.io',
  baseUrl: '/docs/',

  organizationName: 'Peixinho',
  projectName: 'LazyMePHP',

  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          routeBasePath: '/',
          editUrl: 'https://github.com/Peixinho/LazyMePHP/tree/main/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      navbar: {
        title: 'LazyMePHP',
        logo: {
          alt: 'LazyMePHP',
          src: 'img/logo.svg',
          srcDark: 'img/logo-dark.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'docs',
            position: 'left',
            label: 'Docs',
          },
          {
            href: 'https://github.com/Peixinho/LazyMePHP',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              { label: 'Quick Start', to: '/intro' },
              { label: 'ORM', to: '/orm/basic-crud' },
              { label: 'Authentication', to: '/auth/jwt' },
              { label: 'CLI Reference', to: '/cli' },
            ],
          },
          {
            title: 'Links',
            items: [
              { label: 'GitHub', href: 'https://github.com/Peixinho/LazyMePHP' },
              { label: 'Issues', href: 'https://github.com/Peixinho/LazyMePHP/issues' },
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} Duarte Peixinho. MIT License.`,
      },
      prism: {
        theme: require('prism-react-renderer').themes.github,
        darkTheme: require('prism-react-renderer').themes.dracula,
        additionalLanguages: ['php', 'bash', 'json', 'graphql', 'sql'],
      },
      colorMode: {
        defaultMode: 'dark',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

module.exports = config;
