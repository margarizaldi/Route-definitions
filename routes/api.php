<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->name('v1.')->group(function () {
    Route::prefix('/auth')->name('auth.')->group(function () {
        Route::post('/logout', fn () => 'logout')->name('logout');
    });

    // global read-only routes to support the application operations
    // anyone authenticated can access these routes
    Route::prefix('/app')->name('app.')->middleware('auth')->group(function () {
        Route::prefix('/locations')->name('locations.')->group(function () {
            Route::get('/', fn () => 'simple list of active locations, searchable by keywords')->name('index');
            Route::get('/{location}', fn () => 'view location')->name('view');
            Route::get('/{location}/postal-codes', fn () => 'simple list of active postal codes')->name('postalCodes');
            Route::get('/{location}/postal-codes/{postalCode}', fn () => 'view postal code')->name('postalCodes.view');
        });

        Route::prefix('/banks')->name('banks.')->group(function () {
            Route::get('/', fn () => 'simple list of active banks')->name('index');
            Route::get('/{bank}', fn () => 'view bank')->name('view');
        });

        Route::prefix('/couriers')->name('couriers.')->group(function () {
            Route::get('/', fn () => 'simple list of active couriers')->name('index');
            Route::get('/{courier}', fn () => 'view courier')->name('view');
            Route::get('/{courier}/services', fn () => 'simple list of active courier services')->name('services');
            Route::get('/{courier}/services/{service}', fn () => 'view courier service')->name('services.view');
        });
    });

    // routes under user context
    Route::prefix('/user')->name('user.')->middleware('auth', 'setUserLocale')->group(function () {
        Route::prefix('/me')->name('me.')->group(function () {
            Route::get('/', fn () => 'current user details')->name('view');
            Route::put('/', fn () => 'update user details')->name('update');
        });

        // relationship: teams (many-to-many / optional) --- either as the owner or as a member
        Route::prefix('/teams')->name('teams.')->group(function () {
            Route::get('/', fn () => 'simple list of teams with few details')->name('index');
            Route::prefix('/records')->name('records.')->group(function () {
                Route::get('/', fn () => 'paginated full list of teams associated to the user, either as owner or member')->name('list');
                Route::post('/', fn () => 'create team')->name('create');
                Route::post('/check-subdomain', fn () => 'check subdomain availability')->name('checkSubdomain');
            });
        });

        // relationship: agent (one-to-one / optional)
        Route::prefix('/agent')->name('agent.')->group(function () {
            Route::post('/', fn () => 'create an agent profile, forbidden if user already has an agent profile. once the agent profile is created, it cannot be deleted.')->name('create');
            Route::get('/', fn () => 'view the agent profile')->name('view');
            Route::put('/', fn () => 'update the agent profile')->name('update');

            // relationship: teams (many-to-many)
            Route::prefix('/teams')->name('teams.')->group(function () {
                Route::get('/', fn () => 'simple list of teams attached to agent')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of teams attached to the user as agent')->name('list');
                });
            });
        });
    });

    // routes under team context, team id must be set in request header as X-Team-Id
    Route::prefix('/team')->name('team.')->middleware('auth', 'setTeamContext')->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Wallets and transactions related deposits and withdrawals
        |--------------------------------------------------------------------------
        */
        Route::prefix('/wallet')->name('wallet.')->group(function () {
            Route::get('/', fn () => 'view team wallet balance details (balance, pending balance, pending deposit count & amount, and pending withdrawal count & amount)')->name('view');
            Route::get('/balance', fn () => 'view team wallet balance')->name('balance');
            Route::get('/transactions', fn () => 'list team wallet deposits and withdrawals')->name('transactions');
            Route::get('/transactions/{transaction}', fn () => 'view team wallet deposit or withdrawal')->name('transactions.view');
            Route::post('/deposit', fn () => 'deposit to team wallet')->name('deposit');
            Route::post('/withdraw', fn () => 'withdraw from team wallet')->name('withdraw');
        });

        /*
        |--------------------------------------------------------------------------
        | Transactions related to orders, shipping payment, cash on delivery, etc.
        |--------------------------------------------------------------------------
        */

        Route::prefix('/transactions')->name('transactions.')->group(function () {
            Route::get('/', fn () => 'simple list of team transactions')->name('index');
            Route::prefix('/records')->name('records.')->group(function () {
                Route::get('/', fn () => 'paginated full list of team transactions')->name('list');
                Route::get('/{transaction}', fn () => 'view team transaction')->name('view');
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Members
        |--------------------------------------------------------------------------
        */

        Route::prefix('/members')->name('members.')->group(function () {
            Route::get('/', fn () => 'simple list of members')->name('index');
            Route::prefix('/records')->name('records.')->group(function () {
                Route::get('/', fn () => 'paginated full list of members')->name('list');
                Route::get('/{member}', fn () => 'view member and its configuration related to team')->name('view');
                Route::put('/{member}', fn () => 'update member configuration related to team')->name('update');
                Route::delete('/{member}', fn () => 'detach single member from team')->name('detach');
            });
            Route::prefix('/manage')->name('manage.')->group(function () {
                Route::delete('/detach', fn () => 'detach multiple members from team')->name('detach');
            });

            Route::prefix('/invitations')->name('invitations.')->group(function () {
                Route::get('/', fn () => 'simple list of member invitations')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of member invitations')->name('list');
                    Route::post('/', fn () => 'create (and send) member invitation')->name('create');
                    Route::get('/{invitation}', fn () => 'view member invitation')->name('view');
                    Route::put('/{invitation}', fn () => 'update member invitation')->name('update');
                    Route::delete('/{invitation}', fn () => 'destroy (cancel) single member invitation')->name('destroy');
                    Route::post('/{invitation}/resend', fn () => 'resend member invitation')->name('resend');
                });
                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::delete('/destroy', fn () => 'destroy (cancel) multiple member invitations')->name('destroy');
                });
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Agents
        |--------------------------------------------------------------------------
        */

        Route::prefix('/agents')->name('agents.')->group(function () {
            Route::get('/', fn () => 'simple list of agents')->name('index');
            Route::prefix('/records')->name('records.')->group(function () {
                Route::get('/', fn () => 'paginated full list of agents')->name('list');
                Route::get('/{agent}', fn () => 'view agent and its configuration related to team')->name('view');
                Route::delete('/{agent}', fn () => 'detach single agent from team')->name('detach');

                // agent group relationship (many-to-many)
                Route::prefix('/{agent}/groups')->name('groups.')->group(function () {
                    Route::get('/', fn () => 'simple list of agent groups attached to agent')->name('index');
                });
            });
            
            Route::prefix('/invitations')->name('invitations.')->group(function () {
                Route::get('/', fn () => 'simple list of agent invitations')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of agent invitations')->name('list');
                    Route::post('/', fn () => 'create (and send) agent invitation')->name('send');
                    Route::get('/{invitation}', fn () => 'view agent invitation')->name('view');
                    Route::post('/{invitation}/resend', fn () => 'resend agent invitation')->name('resend');
                    Route::put('/{invitation}', fn () => 'update agent invitation')->name('update');
                    Route::delete('/{invitation}', fn () => 'destroy (cancel) single agent invitation')->name('destroy');
                });
                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::delete('/destroy', fn () => 'destroy (cancel) multiple agent invitations')->name('destroy');
                });
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Team Assets:
        | - Agent groups
        | - Pages
        | - Products
        | - Warehouses
        |--------------------------------------------------------------------------
        */

        Route::prefix('/assets')->name('assets.')->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Agent Groups
            |--------------------------------------------------------------------------
            */
            Route::prefix('/agent-groups')->name('agentGroups.')->group(function () {
                Route::get('/', fn () => 'simple list of agent groups')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of agent groups')->name('list');
                    Route::post('/', fn () => 'create a new agent group')->name('create');
                    Route::get('/{agentGroup}', fn () => 'view agent group')->name('view');
                    Route::put('/{agentGroup}', fn () => 'update agent group')->name('update');
                    Route::delete('/{agentGroup}', fn () => 'remove agent group')->name('destroy');

                    // agents relationship (many-to-many)
                    Route::prefix('/{agentGroup}/agents')->name('agents.')->group(function () {
                        Route::get('/', fn () => 'simple list of agents in agent group')->name('index');
                        Route::prefix('/records')->name('records.')->group(function () {
                            Route::get('/', fn () => 'paginated full list of agents in agent group')->name('list');
                            Route::get('/{agent}', fn () => 'view single agent with pivot values in agent group')->name('view');
                            Route::put('/{agent}', fn () => 'update single agent pivot values in agent group')->name('update');
                            Route::delete('/{agent}', fn () => 'detach single agent from agent group')->name('detach');
                        });
                        Route::prefix('/manage')->name('manage.')->group(function () {
                            Route::post('/attach', fn () => 'attach multiple agents to agent group')->name('attach');
                            Route::put('/update', fn () => 'update agents attachements in agent group (sync) and the pivot values')->name('update');
                            Route::delete('/detach', fn () => 'detach multiple agents from agent group')->name('detach');
                        });
                    });

                    // relationship: pages (one-to-many)
                    Route::prefix('/{agentGroup}/pages')->name('pages.')->group(function () {
                        Route::get('/', fn () => 'simple list of pages in agent group')->name('index');
                        Route::prefix('/manage')->name('manage.')->group(function () {
                            Route::post('/attach', fn () => 'attach multiple pages to agent group')->name('attach');
                            Route::delete('/detach', fn () => 'detach multiple pages from agent group')->name('detach');
                        });
                    });
                });
                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::delete('/destroy', fn () => 'destroy (cancel) multiple agent groups')->name('destroy');
                });
            });

            /*
            |--------------------------------------------------------------------------
            | Pages
            |--------------------------------------------------------------------------
            */

            Route::prefix('/pages')->name('pages.')->group(function () {
                Route::get('/', fn () => 'simple list of pages')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of pages')->name('list');
                    Route::post('/', fn () => 'create new page')->name('create');
                    Route::get('/{page}', fn () => 'view page')->name('view');
                    Route::put('/{page}', fn () => 'update page')->name('update');
                    Route::patch('/{page}/action', fn () => 'do some available action such as archive or restore, e.g.: { "action": "archive" }')->name('action');
                    Route::delete('/{page}', fn () => 'destroy page')->name('destroy');

                    // relationship: setting (one-to-one)
                    Route::prefix('{page}/setting')->name('setting.')->group(function () {
                        Route::get('/', fn () => 'view the page setting')->name('view');
                        Route::put('/', fn () => 'update the page setting')->name('update');
                    });

                    // relationship: product (many-to-one)
                    Route::prefix('{page}/product')->name('product.')->group(function () {
                        Route::get('/', fn () => 'view product attached to page')->name('view');
                        Route::patch('/change', fn () => 'change the product attached to page, e.g.: { "product_id": "TPD123qweasd" }')->name('change'); // updating product_id
                    });

                    // relationship : agent group (many-to-one / optional)
                    Route::prefix('{page}/agent-group')->name('agentGroup.')->group(function () {
                        Route::get('/', fn () => 'view agent group attached to page')->name('view');
                        Route::patch('/change', fn () => 'add, replace, or remove the agent group from page, e.g.: { "agent_group_id": "AGG123qweasd" }')->name('change'); // updating agent_group_id
                    });
                });

                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::post('/archive', fn () => 'archive multiple pages')->name('archive');
                    Route::post('/restore', fn () => 'restore multiple pages')->name('restore');
                    Route::delete('/destroy', fn () => 'destroy multiple pages')->name('destroy');
                });
            });

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            Route::prefix('/products')->name('products.')->group(function () {
                Route::get('/', fn () => 'simple list of products')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of products')->name('list');
                    Route::post('/', fn () => 'create new product')->name('create');
                    Route::get('/{product}', fn () => 'view product')->name('view');
                    Route::put('/{product}', fn () => 'update product')->name('update');
                    Route::patch('/{product}/action', fn () => 'do some available action such as archive or restore, e.g.: { "action": "archive" }')->name('action');
                    Route::delete('/{product}', fn () => 'destroy team product')->name('destroy');

                    // relationship : variants (one-to-many / optional)
                    Route::prefix('{product}/variants')->name('variants.')->group(function () {
                        Route::get('/', fn () => 'simple list of variants of the product')->name('index');
                        Route::prefix('/records')->name('records.')->group(function () {
                            Route::get('/', fn () => 'paginated full list of variants of the product')->name('list');
                            Route::put('/{variant}', fn () => 'update single variant of the product')->name('update');
                            Route::delete('/{variant}', fn () => 'destroy single variant of the product')->name('destroy');
                        });

                        Route::prefix('/manage')->name('manage.')->group(function () {
                            Route::post('/create', fn () => 'create multiple variants of the product')->name('create');
                            Route::put('/update', fn () => 'update multiple variants of the product')->name('update');
                            Route::delete('/destroy', fn () => 'destroy multiple variants of the product')->name('destroy');
                        });
                    });

                    // relationship : pages (one-to-many / optional)
                    Route::prefix('{product}/pages')->name('pages.')->group(function () {
                        Route::get('/', fn () => 'simple list of pages attached to product')->name('list');

                        Route::prefix('/records')->name('records.')->group(function () {
                            Route::get('/', fn () => 'paginated full list of pages attached to product')->name('list');
                            Route::delete('/{page}', fn () => 'detach single page from product')->name('detach');
                        });

                        Route::prefix('/manage')->name('manage.')->group(function () {
                            Route::post('/attach', fn () => 'attach multiple pages to product')->name('attach');
                            Route::delete('/detach', fn () => 'detach multiple pages from product')->name('detach');
                        });
                    });

                    // relationship : warehouses (many-to-many)
                    Route::prefix('{product}/warehouses')->name('warehouses.')->group(function () {
                        Route::get('/', fn () => 'simple list of warehouses attached to product')->name('list');

                        Route::prefix('/records')->name('records.')->group(function () {
                            Route::get('/', fn () => 'paginated full list of warehouses attached to product')->name('list');
                            Route::delete('/{warehouse}', fn () => 'detach single warehouse from product')->name('detach');
                        });

                        Route::prefix('/manage')->name('manage.')->group(function () {
                            Route::post('/attach', fn () => 'attach multiple warehouses to product')->name('attach');
                            Route::delete('/detach', fn () => 'detach multiple warehouses from product')->name('detach');
                        });
                    });
                });

                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::post('/archive', fn () => 'archive multiple products')->name('archive');
                    Route::post('/restore', fn () => 'restore multiple products')->name('restore');
                    Route::delete('/destroy', fn () => 'destroy multiple products')->name('destroy');
                });
            });


            /*
            |--------------------------------------------------------------------------
            | Warehouses
            |--------------------------------------------------------------------------
            */

            Route::prefix('/warehouses')->name('warehouses.')->group(function () {
                Route::get('/', fn () => 'simple list of warehouses')->name('index');
                Route::prefix('/records')->name('records.')->group(function () {
                    Route::get('/', fn () => 'paginated full list of warehouses')->name('list');
                    Route::post('/', fn () => 'create new warehouse')->name('create');
                    Route::get('/{warehouse}', fn () => 'view warehouse')->name('view');
                    Route::put('/{warehouse}', fn () => 'update warehouse')->name('update');
                    Route::patch('/{warehouse}/action', fn () => 'do some available action such as archive or restore, e.g.: { "action": "archive" }')->name('action');
                    Route::delete('/{warehouse}', fn () => 'destroy warehouse')->name('destroy');

                    // relationship : products (many-to-many / optional)
                    Route::prefix('{warehouse}/products')->name('products.')->group(function () {
                        Route::get('/', fn () => 'simple list of products attached to warehouse')->name('list');

                        Route::prefix('/records')->name('records.')->group(function () {
                            Route::get('/', fn () => 'paginated full list of products attached to warehouse')->name('list');
                            Route::post('/', fn () => 'attach single or multiple products to warehouse')->name('attach');
                            Route::delete('/{product}', fn () => 'detach single product from warehouse')->name('detach');
                        });
                    });
                });

                Route::prefix('/manage')->name('manage.')->group(function () {
                    Route::post('/archive', fn () => 'archive multiple warehouses')->name('archive');
                    Route::post('/restore', fn () => 'restore multiple warehouses')->name('restore');
                    Route::delete('/destroy', fn () => 'destroy multiple warehouses')->name('destroy');
                });
            });
        });
    });
});
