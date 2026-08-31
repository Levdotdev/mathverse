import { createClient } from "npm:@supabase/supabase-js@2";
import webpush from "npm:web-push@3.6.7";

type PushPayload = {
  title?: string;
  body?: string;
  url?: string;
  tag?: string;
  user_ids?: string[];
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
  const adminPushSecret = Deno.env.get("ADMIN_PUSH_SECRET") ?? "";

  if (
    !supabaseUrl ||
    !serviceKey ||
    !vapidPublicKey ||
    !vapidPrivateKey ||
    !vapidSubject ||
    !adminPushSecret
  ) {
    return json({ message: "Web Push secrets are incomplete." }, 503);
  }
  if (
    !safeEqual(
      request.headers.get("x-mathverse-push-secret") ?? "",
      adminPushSecret,
    )
  ) {
    return json({ message: "Unauthorized." }, 401);
  }

  let payload: PushPayload;
  try {
    const decoded = await request.json();
    if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
      return json({ message: "The JSON payload must be an object." }, 422);
    }
    payload = decoded as PushPayload;
  } catch (_error) {
    return json({ message: "Invalid JSON payload." }, 422);
  }

  const targetUserIds = normalizeUserIds(payload.user_ids);
  if (payload.user_ids !== undefined && targetUserIds === null) {
    return json({ message: "user_ids must contain 1 to 100 UUIDs." }, 422);
  }

  const notification = JSON.stringify({
    title: String(payload.title ?? "MathVerse Notification").slice(0, 100),
    body: String(payload.body ?? "A new item needs your attention.").slice(
      0,
      240,
    ),
    url: safeAppPath(payload.url),
    tag: String(payload.tag ?? "mathverse-notification").slice(0, 100),
  });

  const supabase = createClient(supabaseUrl, serviceKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });
  let subscriptionQuery = supabase
    .from("push_subscriptions")
    .select("id,user_id,endpoint,p256dh,auth,profiles!inner(role)");

  // No recipient list intentionally retains the original all-admin behavior
  // used by teacher-registration and quiz-report alerts.
  subscriptionQuery = targetUserIds === null
    ? subscriptionQuery.eq("profiles.role", "admin")
    : subscriptionQuery.in("user_id", targetUserIds);

  const { data: subscriptions, error } = await subscriptionQuery;

  if (error) return json({ message: error.message }, 500);

  webpush.setVapidDetails(vapidSubject, vapidPublicKey, vapidPrivateKey);

  let sent = 0;
  let failed = 0;
  let expired = 0;
  for (const subscription of subscriptions ?? []) {
    try {
      await webpush.sendNotification(
        {
          endpoint: subscription.endpoint,
          keys: {
            p256dh: subscription.p256dh,
            auth: subscription.auth,
          },
        },
        notification,
        { TTL: 3600 },
      );
      sent++;
    } catch (pushError) {
      const statusCode = Number(
        (pushError as { statusCode?: number })?.statusCode ?? 0,
      );
      if (statusCode === 404 || statusCode === 410) {
        await supabase
          .from("push_subscriptions")
          .delete()
          .eq("id", subscription.id);
        expired++;
      } else {
        failed++;
        console.error("MathVerse browser push rejected", {
          subscriptionId: subscription.id,
          statusCode,
          message:
            pushError instanceof Error ? pushError.message : String(pushError),
        });
      }
    }
  }

  return json({ sent, failed, expired, total: subscriptions?.length ?? 0 });
});

function safeEqual(left: string, right: string): boolean {
  const leftBytes = new TextEncoder().encode(left);
  const rightBytes = new TextEncoder().encode(right);
  if (leftBytes.length !== rightBytes.length) return false;

  let difference = 0;
  for (let index = 0; index < leftBytes.length; index++) {
    difference |= leftBytes[index] ^ rightBytes[index];
  }
  return difference === 0;
}

function normalizeUserIds(value: unknown): string[] | null {
  if (value === undefined) return null;
  if (!Array.isArray(value) || value.length < 1 || value.length > 100) {
    return null;
  }

  const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
  const ids = [...new Set(value.map((item) => String(item)))];

  return ids.every((item) => uuid.test(item)) ? ids : null;
}

function safeAppPath(value: unknown): string {
  const path = String(value ?? "/");
  return path.startsWith("/") && !path.startsWith("//") ? path : "/";
}

function json(payload: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "content-type": "application/json; charset=utf-8" },
  });
}
