// @ts-check

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  docs: [
    'intro',
    'configuration',
    'routing',
    {
      type: 'category',
      label: 'ORM',
      collapsed: false,
      items: [
        'orm/basic-crud',
        'orm/query-builder',
        'orm/joins-aggregates',
        'orm/raw-queries',
        'orm/relationships',
        'orm/soft-deletes',
        'orm/validation',
        'orm/events',
        'orm/scopes',
        'orm/query-caching',
        'orm/factories-seeders',
      ],
    },
    {
      type: 'category',
      label: 'Authentication',
      items: [
        'auth/jwt',
        'auth/rbac',
      ],
    },
    {
      type: 'category',
      label: 'HTTP',
      items: [
        'http/rate-limiting',
        'http/form-request',
        'http/api-resources',
        'http/request-id',
      ],
    },
    {
      type: 'category',
      label: 'Database',
      items: [
        'database/migrations',
        'database/schema-cache',
      ],
    },
    'cache',
    'mail',
    'notifications',
    'queue',
    'storage',
    'tenancy',
    {
      type: 'category',
      label: 'API',
      items: [
        'api/graphql',
        'api/openapi',
        'api/health',
      ],
    },
    'crud-ui',
    'audit-logging',
    'batman',
    'cli',
    'security',
  ],
};

export default sidebars;
