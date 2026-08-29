import { createClient } from "npm:@supabase/supabase-js@2";
import webpush from "npm:web-push@3.6.7";

type PushPayload = {
  title?: string;
  body?: string;
  url?: string;
  tag?: string;
};

Deno.serve(async (request: Request) => {
  if (request.method !== "POST") {
    return json({ message: "Method not allowed." }, 405);
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL") ?? "";
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "";
  const vapidPublicKey = Deno.env.get("VAPID_PUBLIC_KEY") ?? "";
  const vapidPrivateKey = Deno.env.get("VAPID_PRIVATE_KEY") ?? "";
  const vapidSubject = Deno.env.get("VAPID_SUBJECT") ?? "";

  if (request.headers.get("authorization") !== `Bearer ${serviceKey}`) {
    return json({ message: "Unauthorized." }, 401);
  }
  if (!supabaseUrl || !serviceKey || !vapidPublicKey || !vapidPrivateKey || !vapidSubject) {
    return json({ message: "Web Push secrets are incomplete." }, 503);
  }

  let payload: PushPayload;
  try {
    payload = await request.json();
  } catch (_error) {
    return json({ message: "Invalid JSON payload." }, 422);
  }

  const notification = JSON.stringify({
    title: String(payload.title ?? "MathVerse Admin Alert").slice(0, 100),
    body: String(payload.body ?? "A new item needs your attention.").slice(0, 240),
    url: safeAdminPath(payload.url),
    tag: String(payload.tag ?? "mathverse-admin-alert").slice(0, 100),
  });

  const supabase = createClient(supabaseUrl, serviceKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });
  const { data: subscriptions, error } = await supabase
    .from("push_subscriptions")
    .select("id,endpoint,p256dh,auth,profiles!inner(role)")
    .eq("profiles.role", "admin");

  if (error) return json({ message: error.message }, 500);

  webpush.setVapidDetails(vapidSubject, vapidPublicKey, vapidPrivateKey);

  let sent = 0;
  let expired = 0;
  for (const subscription of subscriptions ?? []) {
    try {
      await webpush.sendNotification({
        endpoint: subscription.endpoint,
        keys: {
          p256dh: subscription.p256dh,
          auth: subscription.auth,
        },
      }, notification, { TTL: 3600 });
      sent++;
    } catch (pushError) {
      const statusCode = Number(
        (pushError as { statusCode?: number })?.statusCode ?? 0,
      );
      if (statusCode === 404 || statusCode === 410) {
        await supabase.from("push_subscriptions").delete().eq("id", subscription.id);
        expired++;
      }
    }
  }

  return json({ sent, expired, total: subscriptions?.length ?? 0 });
});

function safeAdminPath(value: unknown): string {
  const path = String(value ?? "/admin/dashboard");
  return path.startsWith("/admin/") || path === "/admin/dashboard"
    ? path
    : "/admin/dashboard";
}

function json(payload: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "content-type": "application/json; charset=utf-8" },
  });
}
